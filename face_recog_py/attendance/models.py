from django.db import models
from django.utils import timezone

class Student(models.Model):
    name = models.CharField(max_length=100)
    student_id = models.CharField(max_length=20, unique=True)
    photo = models.ImageField(upload_to='students/')  # Primary photo
    face_encoding = models.BinaryField(null=True)  # Primary encoding (for backward compatibility)
    
    # New fields for multiple photos and encodings
    photos_metadata = models.JSONField(default=list, blank=True)  # Store photo paths and angles
    face_encodings = models.JSONField(default=list, blank=True)   # Store multiple encodings as base64
    
    def __str__(self):
        return f"{self.name} ({self.student_id})"
    
    def get_total_photos(self):
        """Get total number of photos including primary"""
        return 1 + len(self.photos_metadata)
    
    def add_photo_metadata(self, photo_path, angle, encoding_b64):
        """Add metadata for additional photos"""
        if not self.photos_metadata:
            self.photos_metadata = []
        if not self.face_encodings:
            self.face_encodings = []
            
        self.photos_metadata.append({
            'path': photo_path,
            'angle': angle,
            'timestamp': str(timezone.now())
        })
        self.face_encodings.append(encoding_b64)

class AttendanceRecord(models.Model):
    student = models.ForeignKey(Student, on_delete=models.CASCADE)
    timestamp = models.DateTimeField(auto_now_add=True)
    status = models.CharField(max_length=10, choices=[('Present', 'Present'), ('Late', 'Late'), ('Absent', 'Absent')])

    def __str__(self):
        return f"{self.student} - {self.timestamp} - {self.status}"
