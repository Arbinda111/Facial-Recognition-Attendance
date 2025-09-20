from rest_framework.views import APIView
from rest_framework.response import Response
from rest_framework.parsers import MultiPartParser
from .models import Student, AttendanceRecord
from .serializers import StudentSerializer
from .face_recognition_opencv_simple import (
    get_opencv_face_recognition_model, 
    extract_face_features_opencv, 
    train_opencv_face_recognition_model,
    predict_student_identity_opencv
)
import numpy as np
import base64
from django.utils import timezone
import logging
import uuid

logger = logging.getLogger(__name__)

class RegisterStudentView(APIView):
    parser_classes = [MultiPartParser]

    def post(self, request):
        try:
            name = request.data.get('name')
            student_id = request.data.get('student_id')
            image_file = request.data.get('photo')

            # Validate required fields
            if not name or not student_id or not image_file:
                return Response({
                    'error': 'Missing required fields: name, student_id, and photo'
                }, status=400)

            # Validate inputs
            if len(name.strip()) < 2:
                return Response({
                    'error': 'Student name must be at least 2 characters long'
                }, status=400)

            if len(student_id.strip()) < 1:
                student_id = str(uuid.uuid4())[:8]

            # Check if student_id already exists
            if Student.objects.filter(student_id=student_id).exists():
                return Response({
                    'error': f'Student with ID {student_id} already exists'
                }, status=400)

            # Extract face encoding using improved ML method
            try:
                face_encoding = extract_face_features_opencv(image_file, is_registration=True)
                face_encoding_bytes = face_encoding.tobytes()
            except ValueError as e:
                return Response({'error': str(e)}, status=400)
            except Exception as e:
                return Response({
                    'error': f'Failed to process face: {str(e)}'
                }, status=400)

            # Create student
            student = Student.objects.create(
                name=name.strip(),
                student_id=student_id.strip(),
                photo=image_file,
                face_encoding=face_encoding_bytes
            )

            # Add to training data and retrain model
            try:
                # Get ML model
                model = get_opencv_face_recognition_model()
                model.add_training_sample(face_encoding, student_id.strip())
                
                # Always retrain the model with all students from database
                accuracy, stats = train_opencv_face_recognition_model()
                logger.info(f"Model retrained with accuracy: {accuracy:.3f}")
                
                return Response({
                    'message': 'Student registered successfully',
                    'student': StudentSerializer(student).data,
                    'model_trained': True,
                    'accuracy': accuracy,
                    'training_samples': stats['n_samples']
                })
            except Exception as e:
                # Even if model training fails, student is still registered
                logger.error(f"Model training failed: {str(e)}")
                return Response({
                    'message': 'Student registered successfully, but model training failed',
                    'student': StudentSerializer(student).data,
                    'training_error': str(e)
                })

        except Exception as e:
            logger.error(f"Registration failed: {str(e)}")
            return Response({'error': f'Registration failed: {str(e)}'}, status=500)


class AttendanceView(APIView):
    parser_classes = [MultiPartParser]

    def post(self, request):
        try:
            image_file = request.data.get('photo')
            if not image_file:
                return Response({'error': 'No photo provided'}, status=400)
            
            # Extract face encoding
            try:
                uploaded_encoding = extract_face_features_opencv(image_file, is_registration=False)
            except ValueError as e:
                return Response({'error': str(e)}, status=400)
            except Exception as e:
                return Response({'error': f'Face detection failed: {str(e)}'}, status=400)

            # Check if model is trained
            model = get_opencv_face_recognition_model()
            if not model.is_trained:
                return Response({
                    'error': 'Face recognition model is not trained. Please ensure at least 2 students are registered.'
                }, status=400)

            # Predict identity using ML model - Very low threshold for development/testing
            predicted_student_id, confidence = predict_student_identity_opencv(uploaded_encoding, threshold=0.10)
            
            if predicted_student_id is None:
                return Response({
                    'status': 'error',
                    'message': f'Face not recognized (confidence: {confidence:.1%})',
                    'confidence': float(confidence),
                    'threshold': 0.10,
                    'suggestion': 'Try taking a clearer photo or register with multiple angles',
                    'debug_info': f'Confidence {confidence:.3f} is below threshold 0.10'
                }, status=404)

            # Get student object
            try:
                student = Student.objects.get(student_id=predicted_student_id)
            except Student.DoesNotExist:
                return Response({
                    'error': 'Student record not found in database'
                }, status=404)

            # Check if attendance already marked today
            today = timezone.now().date()
            existing = AttendanceRecord.objects.filter(
                student=student,
                timestamp__date=today
            ).first()
            
            if existing:
                return Response({
                    'message': 'Attendance already marked today',
                    'student_id': student.student_id,
                    'student_name': student.name,
                    'confidence': float(confidence),
                    'timestamp': existing.timestamp.isoformat()
                })
            
            # Mark attendance
            attendance = AttendanceRecord.objects.create(
                student=student,
                status='Present'
            )
            
            return Response({
                'status': 'success',
                'message': 'Attendance marked successfully',
                'student_id': student.student_id,
                'student_name': student.name,
                'confidence': float(confidence),
                'timestamp': attendance.timestamp.isoformat()
            })
            
        except Exception as e:
            logger.error(f"Attendance marking failed: {str(e)}")
            return Response({'error': f'Attendance marking failed: {str(e)}'}, status=500)


class TrainModelView(APIView):
    """Endpoint to manually trigger model training"""
    
    def post(self, request):
        try:
            accuracy, stats = train_opencv_face_recognition_model()
            return Response({
                'message': 'Model trained successfully',
                'accuracy': accuracy,
                'stats': stats
            })
        except ValueError as e:
            return Response({'error': str(e)}, status=400)
        except Exception as e:
            return Response({'error': f'Training failed: {str(e)}'}, status=500)


class ModelStatsView(APIView):
    """Get current model statistics"""
    
    def get(self, request):
        model = get_opencv_face_recognition_model()
        stats = model.get_model_stats()
        return Response(stats)


class BatchRegisterView(APIView):
    """Register multiple students and train model in batch"""
    parser_classes = [MultiPartParser]
    
    def post(self, request):
        try:
            # This would handle multiple student registrations at once
            # Useful for initial setup or bulk imports
            students_data = request.data.get('students', [])
            
            if not students_data:
                return Response({'error': 'No student data provided'}, status=400)
            
            registered_students = []
            errors = []
            
            for student_data in students_data:
                try:
                    name = student_data.get('name')
                    student_id = student_data.get('student_id')
                    image_file = student_data.get('photo')
                    
                    if not all([name, student_id, image_file]):
                        errors.append(f"Missing data for student: {student_id or 'unknown'}")
                        continue
                    
                    # Check if already exists
                    if Student.objects.filter(student_id=student_id).exists():
                        errors.append(f"Student {student_id} already exists")
                        continue
                    
                    # Extract encoding
                    face_encoding = extract_face_features_opencv(image_file, is_registration=True)
                    face_encoding_bytes = face_encoding.tobytes()
                    
                    # Create student
                    student = Student.objects.create(
                        name=name.strip(),
                        student_id=student_id.strip(),
                        photo=image_file,
                        face_encoding=face_encoding_bytes
                    )
                    
                    registered_students.append(student)
                    
                except Exception as e:
                    errors.append(f"Failed to register {student_id}: {str(e)}")
            
            # Train model with all registered students
            try:
                if len(registered_students) >= 2:
                    accuracy, stats = train_opencv_face_recognition_model()
                    training_success = True
                    training_message = f"Model trained with accuracy: {accuracy:.3f}"
                else:
                    training_success = False
                    training_message = "Not enough students to train model"
            except Exception as e:
                training_success = False
                training_message = f"Training failed: {str(e)}"
            
            return Response({
                'registered_count': len(registered_students),
                'registered_students': [s.student_id for s in registered_students],
                'errors': errors,
                'model_trained': training_success,
                'training_message': training_message
            })
            
        except Exception as e:
            return Response({'error': f'Batch registration failed: {str(e)}'}, status=500)


class MultiAngleRegisterView(APIView):
    """Register student with 5 photos from different angles for better recognition"""
    parser_classes = [MultiPartParser]
    
    def post(self, request):
        try:
            name = request.data.get('name')
            student_id = request.data.get('student_id')
            
            # Required angles for best recognition
            required_angles = ['front', 'left', 'right', 'up', 'slight_left']
            photo_files = {}
            
            # Get photos for each angle
            for angle in required_angles:
                photo_file = request.data.get(f'photo_{angle}')
                if photo_file:
                    photo_files[angle] = photo_file
            
            # At minimum, we need front photo
            if 'front' not in photo_files:
                return Response({
                    'error': 'Front angle photo is required',
                    'required_angles': required_angles,
                    'expected_fields': [f'photo_{angle}' for angle in required_angles]
                }, status=400)
            
            # Validate required fields
            if not name or not student_id:
                return Response({
                    'error': 'Name and student_id are required'
                }, status=400)
            
            # Check if student already exists
            if Student.objects.filter(student_id=student_id).exists():
                return Response({
                    'error': f'Student with ID {student_id} already exists'
                }, status=400)
            
            # Process front photo first (primary)
            try:
                front_encoding = extract_face_features_opencv(photo_files['front'], is_registration=True)
                front_encoding_bytes = front_encoding.tobytes()
            except Exception as e:
                return Response({
                    'error': f'Failed to process front photo: {str(e)}'
                }, status=400)
            
            # Create student with front photo
            student = Student.objects.create(
                name=name.strip(),
                student_id=student_id.strip(),
                photo=photo_files['front'],
                face_encoding=front_encoding_bytes
            )
            
            model = get_opencv_face_recognition_model()
            model.add_training_sample(front_encoding, student_id)
            
            # Process additional angle photos
            processed_angles = ['front']
            additional_encodings = []
            
            for angle in required_angles[1:]:  # Skip front (already processed)
                if angle in photo_files:
                    try:
                        # Extract features
                        features = extract_face_features_opencv(photo_files[angle], is_registration=True)
                        encoding_b64 = base64.b64encode(features.tobytes()).decode('utf-8')
                        additional_encodings.append(encoding_b64)
                        
                        # Add to training data
                        model.add_training_sample(features, student_id)
                        
                        # Save photo metadata  
                        from django.core.files.storage import default_storage
                        photo_name = f"students/{student_id}_{angle}_{uuid.uuid4().hex[:8]}.jpg"
                        photo_path = default_storage.save(photo_name, photo_files[angle])
                        
                        student.add_photo_metadata(photo_path, angle, encoding_b64)
                        processed_angles.append(angle)
                        
                    except Exception as e:
                        print(f"Failed to process {angle} photo: {e}")
                        continue
            
            # Save student with additional encodings
            student.save()
            
            # Trigger model retraining with all angles
            try:
                accuracy, stats = train_opencv_face_recognition_model()
                training_message = f"Model retrained successfully with {len(processed_angles)} angles (accuracy: {accuracy:.1%})"
            except Exception as e:
                training_message = f"Model retraining failed: {e}"
            
            return Response({
                'status': 'success',
                'message': 'Student registered successfully with multiple angles',
                'student': {
                    'id': student.id,
                    'name': student.name,
                    'student_id': student.student_id,
                    'total_photos': len(processed_angles),
                    'angles_captured': processed_angles
                },
                'training_status': training_message,
                'recommendation': f'Captured {len(processed_angles)}/{len(required_angles)} recommended angles for optimal recognition'
            })
            
        except Exception as e:
            logger.error(f"Multi-angle registration failed: {str(e)}")
            return Response({
                'error': f'Registration failed: {str(e)}'
            }, status=500)


class TestFaceMatchView(APIView):
    """Enhanced testing endpoint"""
    parser_classes = [MultiPartParser]
    def post(self, request):
        try:
            image_file = request.data.get('photo')
            target_student_id = request.query_params.get('student_id')
            
            if not image_file:
                return Response({'error': 'No photo provided'}, status=400)
            
            # Extract face encoding
            try:
                uploaded_encoding = extract_face_features_opencv(image_file, is_registration=False)
            except ValueError as e:
                return Response({'error': str(e)}, status=400)
            
            model = get_opencv_face_recognition_model()
            
            if not model.is_trained:
                return Response({'error': 'Model is not trained'}, status=400)
            
            # Get ML prediction
            predicted_id, ml_confidence = predict_student_identity_opencv(uploaded_encoding, threshold=0.5)
            
            # Also test against individual students for comparison
            students = Student.objects.exclude(face_encoding__isnull=True)
            if target_student_id:
                students = students.filter(student_id=target_student_id)
            
            individual_results = []
            for student in students:
                try:
                    if student.face_encoding:
                        known_encoding = np.frombuffer(student.face_encoding, dtype=np.float64)
                        
                        # Calculate cosine similarity
                        cosine_sim = np.dot(known_encoding, uploaded_encoding) / (
                            np.linalg.norm(known_encoding) * np.linalg.norm(uploaded_encoding)
                        )
                        
                        individual_results.append({
                            'student_id': student.student_id,
                            'student_name': student.name,
                            'cosine_similarity': float(cosine_sim),
                            'is_ml_prediction': student.student_id == predicted_id
                        })
                except Exception as e:
                    individual_results.append({
                        'student_id': student.student_id,
                        'error': str(e)
                    })
            
            individual_results.sort(key=lambda x: x.get('cosine_similarity', 0), reverse=True)
            
            return Response({
                'ml_prediction': {
                    'predicted_student_id': predicted_id,
                    'confidence': float(ml_confidence),
                    'threshold_used': 0.5
                },
                'individual_comparisons': individual_results,
                'model_stats': model.get_model_stats()
            })
            
        except Exception as e:
            return Response({'error': f'Test failed: {str(e)}'}, status=500)


# Existing views (updated to work with new system)
class ListStudentsView(APIView):
    def get(self, request):
        students = Student.objects.all()
        model = get_opencv_face_recognition_model()
        
        return Response({
            'students': StudentSerializer(students, many=True).data,
            'count': students.count(),
            'model_stats': model.get_model_stats()
        })


class AttendanceHistoryView(APIView):
    def get(self, request):
        student_id = request.query_params.get('student_id')
        date = request.query_params.get('date')
        
        records = AttendanceRecord.objects.all()
        
        if student_id:
            records = records.filter(student__student_id=student_id)
        
        if date:
            from datetime import datetime
            try:
                date_obj = datetime.strptime(date, '%Y-%m-%d').date()
                records = records.filter(timestamp__date=date_obj)
            except ValueError:
                return Response({'error': 'Invalid date format. Use YYYY-MM-DD'}, status=400)
        
        records = records.select_related('student').order_by('-timestamp')
        
        data = [{
            'id': record.id,
            'student_name': record.student.name,
            'student_id': record.student.student_id,
            'status': record.status,
            'timestamp': record.timestamp.isoformat()
        } for record in records]
        
        return Response({
            'attendance_records': data,
            'count': len(data)
        })