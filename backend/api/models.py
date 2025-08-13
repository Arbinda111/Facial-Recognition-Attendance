from django.db import models

# Create your models here.
from django.db import models
from django.contrib.auth.models import User

class StudentProfile(models.Model):
    user = models.OneToOneField(User, on_delete=models.CASCADE, related_name="student_profile")
    student_id = models.CharField(max_length=50, unique=True)
    phone = models.CharField(max_length=50, blank=True)
    address = models.CharField(max_length=255, blank=True)
    avatar = models.URLField(blank=True)

    def __str__(self):
        return f"{self.user.get_full_name()} ({self.student_id})"

class Course(models.Model):
    code = models.CharField(max_length=20, unique=True)
    name = models.CharField(max_length=120)

    def __str__(self):
        return f"{self.code} - {self.name}"

class Enrollment(models.Model):
    student = models.ForeignKey(StudentProfile, on_delete=models.CASCADE, related_name="enrollments")
    course = models.ForeignKey(Course, on_delete=models.CASCADE, related_name="enrollments")
    semester = models.CharField(max_length=20, default="S2-2025")

class Attendance(models.Model):
    STATUS_CHOICES = (("PRESENT", "PRESENT"), ("ABSENT", "ABSENT"), ("LATE", "LATE"))
    student = models.ForeignKey(StudentProfile, on_delete=models.CASCADE, related_name="attendance")
    course = models.ForeignKey(Course, on_delete=models.CASCADE, related_name="attendance")
    date = models.DateField()
    status = models.CharField(max_length=10, choices=STATUS_CHOICES, default="PRESENT")

    class Meta:
        unique_together = ("student", "course", "date")
