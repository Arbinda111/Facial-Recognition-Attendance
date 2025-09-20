import cv2
import numpy as np
from sklearn.svm import SVC
from sklearn.preprocessing import StandardScaler
import joblib
import os
import base64
from django.conf import settings
from io import BytesIO

class OpenCVFaceRecognition:
    def __init__(self):
        self.model_dir = os.path.join(settings.BASE_DIR, 'ml_models')
        os.makedirs(self.model_dir, exist_ok=True)
        
        self.svm_path = os.path.join(self.model_dir, 'svm_face_model.pkl')
        self.scaler_path = os.path.join(self.model_dir, 'scaler.pkl')
        
        # Initialize OpenCV face detection (using Haar cascades)
        self.face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')
        
        # Initialize SVM and scaler
        self.svm_model = None
        self.scaler = None
        
        # Training data storage
        self.training_data = []
        self.training_labels = []
        
        self.load_models()
    
    def load_models(self):
        """Load trained SVM model and scaler, and populate training data from database"""
        try:
            if os.path.exists(self.svm_path) and os.path.exists(self.scaler_path):
                self.svm_model = joblib.load(self.svm_path)
                self.scaler = joblib.load(self.scaler_path)
                print("✅ Loaded pre-trained model and scaler")
                
                # Load existing training data from database
                self.load_training_data_from_db()
            else:
                print("ℹ️ No pre-trained model found. Will create new one when training.")
        except Exception as e:
            print(f"⚠️ Error loading models: {e}")
            self.svm_model = None
            self.scaler = None
    
    def load_training_data_from_db(self):
        """Load existing student data into training arrays, including multiple photos per student"""
        try:
            # Import here to avoid circular imports
            from .models import Student
            
            students = Student.objects.exclude(face_encoding__isnull=True)
            self.training_data = []
            self.training_labels = []
            
            for student in students:
                try:
                    # Load primary face encoding
                    if student.face_encoding:
                        face_encoding = np.frombuffer(student.face_encoding, dtype=np.float32)
                        self.training_data.append(face_encoding)
                        self.training_labels.append(student.student_id)
                    
                    # Load additional face encodings from multiple photos
                    if student.face_encodings:
                        for encoding_b64 in student.face_encodings:
                            try:
                                # Decode base64 encoding
                                encoding_bytes = base64.b64decode(encoding_b64)
                                face_encoding = np.frombuffer(encoding_bytes, dtype=np.float32)
                                self.training_data.append(face_encoding)
                                self.training_labels.append(student.student_id)
                            except Exception as e:
                                print(f"Error loading additional encoding for {student.student_id}: {e}")
                                
                except Exception as e:
                    print(f"Error loading student {student.student_id}: {e}")
            
            print(f"✅ Loaded {len(self.training_data)} training samples from {students.count()} students")
            
        except Exception as e:
            print(f"⚠️ Error loading training data from database: {e}")
            self.training_data = []
            self.training_labels = []
    
    def extract_features(self, image):
        """Extract comprehensive features from face using multiple methods"""
        try:
            # Convert to grayscale if needed
            if len(image.shape) == 3:
                gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
            else:
                gray = image
            
            # Detect faces with multiple parameter sets
            faces = self.face_cascade.detectMultiScale(gray, 1.1, 3, minSize=(30, 30))
            
            # If no faces found, try with more relaxed parameters
            if len(faces) == 0:
                faces = self.face_cascade.detectMultiScale(gray, 1.05, 2, minSize=(20, 20))
            
            # If still no faces, try very relaxed parameters
            if len(faces) == 0:
                faces = self.face_cascade.detectMultiScale(gray, 1.02, 1, minSize=(15, 15))
            
            if len(faces) == 0:
                raise ValueError("No face detected in image")
            
            # Use the largest face
            face = max(faces, key=lambda x: x[2] * x[3])
            x, y, w, h = face
            
            # Extract face region
            face_roi = gray[y:y+h, x:x+w]
            
            # Resize to standard size
            face_roi = cv2.resize(face_roi, (128, 128))
            
            # Extract multiple types of features
            features = []
            
            # 1. Raw pixel intensities (downsampled for efficiency)
            raw_features = face_roi.flatten().astype(np.float32) / 255.0
            features.extend(raw_features[::8])  # Every 8th pixel to reduce dimensionality
            
            # 2. Histogram of pixel intensities
            hist = cv2.calcHist([face_roi], [0], None, [64], [0, 256])
            features.extend(hist.flatten())
            
            # 3. Statistical features
            features.extend([
                np.mean(face_roi),
                np.std(face_roi),
                np.median(face_roi),
                np.min(face_roi),
                np.max(face_roi)
            ])
            
            # 4. Gradient features (edge detection)
            sobelx = cv2.Sobel(face_roi, cv2.CV_64F, 1, 0, ksize=3)
            sobely = cv2.Sobel(face_roi, cv2.CV_64F, 0, 1, ksize=3)
            gradient_magnitude = np.sqrt(sobelx**2 + sobely**2)
            
            gradient_hist = cv2.calcHist([gradient_magnitude.astype(np.uint8)], [0], None, [32], [0, 256])
            features.extend(gradient_hist.flatten())
            
            # 5. Simple texture features (variance in local patches)
            patch_size = 16
            texture_features = []
            for i in range(0, 128, patch_size):
                for j in range(0, 128, patch_size):
                    patch = face_roi[i:i+patch_size, j:j+patch_size]
                    if patch.size > 0:
                        texture_features.append(np.var(patch))
            
            features.extend(texture_features)
            
            return np.array(features, dtype=np.float32)
            
        except Exception as e:
            raise ValueError(f"Feature extraction failed: {str(e)}")
    
    def add_training_sample(self, features, student_id):
        """Add a training sample to the dataset"""
        self.training_data.append(features)
        self.training_labels.append(student_id)
        print(f"Added training sample for student {student_id}. Total samples: {len(self.training_data)}")
    
    @property
    def is_trained(self):
        """Check if model is trained"""
        return self.svm_model is not None and self.scaler is not None
    
    def train_model(self, X=None, y=None):
        """Train SVM model with extracted features"""
        try:
            # Use instance data if no parameters provided
            if X is None or y is None:
                if len(self.training_data) < 2:
                    raise ValueError("Need at least 2 samples to train model")
                X = np.array(self.training_data)
                y = np.array(self.training_labels)
            
            if len(X) < 2:
                raise ValueError("Need at least 2 samples to train model")
            
            print(f"Training with {len(X)} samples, {X.shape[1]} features each")
            
            # Initialize scaler and SVM
            self.scaler = StandardScaler()
            self.svm_model = SVC(kernel='rbf', probability=True, gamma='scale', C=1.0)
            
            # Scale features
            X_scaled = self.scaler.fit_transform(X)
            
            # Train model and calculate accuracy
            self.svm_model.fit(X_scaled, y)
            
            # Use training accuracy for now (can implement proper validation later)
            accuracy = self.svm_model.score(X_scaled, y)
            scores = [accuracy]
            
            print(f"✅ Model trained! Training accuracy: {accuracy:.3f}")
            
            # Save models
            joblib.dump(self.svm_model, self.svm_path)
            joblib.dump(self.scaler, self.scaler_path)
            
            print(f"✅ Model trained with accuracy: {accuracy:.3f}")
            
            return accuracy, {
                'n_samples': len(X),
                'n_features': X.shape[1],
                'accuracy': accuracy,
                'cv_scores': scores,  # Already a list
                'unique_classes': len(np.unique(y))
            }
            
        except Exception as e:
            raise Exception(f"Training failed: {str(e)}")
    
    def predict(self, features, threshold=0.5):
        """Predict identity from features"""
        if self.svm_model is None or self.scaler is None:
            raise ValueError("Model not trained. Please train the model first.")
        
        try:
            # Scale features
            features_scaled = self.scaler.transform(features.reshape(1, -1))
            
            # Get prediction and probabilities
            prediction = self.svm_model.predict(features_scaled)[0]
            probabilities = self.svm_model.predict_proba(features_scaled)[0]
            confidence = np.max(probabilities)
            
            # Apply threshold
            if confidence < threshold:
                return None, confidence
            
            return prediction, confidence
            
        except Exception as e:
            raise Exception(f"Prediction failed: {str(e)}")
    
    def get_model_stats(self):
        """Get model statistics"""
        if self.svm_model is None:
            return {
                'trained': False,
                'message': 'Model not trained'
            }
        
        return {
            'trained': True,
            'model_type': 'SVM with OpenCV features',
            'kernel': self.svm_model.kernel,
            'n_support_vectors': sum(self.svm_model.n_support_),
            'classes': self.svm_model.classes_.tolist() if hasattr(self.svm_model, 'classes_') else []
        }
    
    def add_multiple_photos(self, student_id, photo_files):
        """Process multiple photos for a student and extract features from each"""
        features_list = []
        processed_photos = []
        
        angles = ['front', 'left', 'right', 'up', 'down']  # Predefined angles
        
        for i, photo_file in enumerate(photo_files):
            try:
                # Extract features from each photo
                features = self.extract_face_features_from_file(photo_file)
                features_list.append(features)
                
                # Store photo metadata
                angle = angles[i % len(angles)]  # Cycle through angles
                processed_photos.append({
                    'features': features,
                    'angle': angle,
                    'encoding_b64': base64.b64encode(features.tobytes()).decode('utf-8')
                })
                
                # Add to training data
                self.add_training_sample(features, student_id)
                
            except Exception as e:
                print(f"Error processing photo {i+1}: {e}")
                continue
        
        return processed_photos
    
    def extract_face_features_from_file(self, image_file):
        """Extract features from uploaded file"""
        # Handle different input types
        if hasattr(image_file, 'read'):
            image_file.seek(0)
            img_array = np.frombuffer(image_file.read(), np.uint8)
        elif isinstance(image_file, bytes):
            img_array = np.frombuffer(image_file, np.uint8)
        elif isinstance(image_file, BytesIO):
            img_array = np.frombuffer(image_file.getvalue(), np.uint8)
        else:
            raise ValueError("Unsupported image format")
        
        # Decode image
        image = cv2.imdecode(img_array, cv2.IMREAD_COLOR)
        if image is None:
            raise ValueError("Could not decode image")
        
        return self.extract_features(image)

# Global instance
_face_recognition_instance = None

def get_opencv_face_recognition_model():
    """Get the global face recognition model instance"""
    global _face_recognition_instance
    if _face_recognition_instance is None:
        _face_recognition_instance = OpenCVFaceRecognition()
    return _face_recognition_instance

def extract_face_features_opencv(image_data, is_registration=True):
    """Extract face features from image data"""
    try:
        # Handle different input types
        if hasattr(image_data, 'read'):
            image_data.seek(0)
            img_array = np.frombuffer(image_data.read(), np.uint8)
        elif isinstance(image_data, bytes):
            img_array = np.frombuffer(image_data, np.uint8)
        elif isinstance(image_data, BytesIO):
            image_data.seek(0)
            img_array = np.frombuffer(image_data.read(), np.uint8)
        else:
            raise ValueError("Unsupported image data type")
        
        # Decode image
        image = cv2.imdecode(img_array, cv2.IMREAD_COLOR)
        if image is None:
            raise ValueError("Could not decode image")
        
        # Get face recognition model and extract features
        model = get_opencv_face_recognition_model()
        features = model.extract_features(image)
        
        return features
        
    except Exception as e:
        raise ValueError(f"Feature extraction failed: {str(e)}")

def train_opencv_face_recognition_model():
    """Train the face recognition model with all registered students"""
    from .models import Student
    
    try:
        # Get all students with face encodings
        students = Student.objects.exclude(face_encoding__isnull=True)
        
        if len(students) < 2:
            raise ValueError("Need at least 2 students to train model")
        
        print(f"Training with {len(students)} students")
        
        X = []
        y = []
        
        for student in students:
            try:
                # Convert stored bytes back to numpy array
                face_encoding = np.frombuffer(student.face_encoding, dtype=np.float32)
                X.append(face_encoding)
                y.append(student.student_id)
            except Exception as e:
                print(f"Error processing student {student.student_id}: {e}")
                continue
        
        if len(X) < 2:
            raise ValueError("Not enough valid student encodings found")
        
        X = np.array(X)
        y = np.array(y)
        
        # Train model
        model = get_opencv_face_recognition_model()
        accuracy, stats = model.train_model(X, y)
        
        return accuracy, stats
        
    except Exception as e:
        raise Exception(f"Training failed: {str(e)}")

def predict_student_identity_opencv(face_features, threshold=0.5):
    """Predict student identity from face features"""
    try:
        model = get_opencv_face_recognition_model()
        prediction, confidence = model.predict(face_features, threshold)
        
        return prediction, confidence
        
    except Exception as e:
        raise Exception(f"Prediction failed: {str(e)}")
