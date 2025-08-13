from rest_framework import serializers
from django.contrib.auth.models import User
from .models import StudentProfile, Course, Enrollment, Attendance

class UserSerializer(serializers.ModelSerializer):
    class Meta:
        model = User
        fields = ["id", "username", "first_name", "last_name", "email", "is_staff"]

class StudentProfileSerializer(serializers.ModelSerializer):
    user = UserSerializer()
    class Meta:
        model = StudentProfile
        fields = ["user", "student_id", "phone", "address", "avatar"]

class CourseSerializer(serializers.ModelSerializer):
    class Meta:
        model = Course
        fields = ["id", "code", "name"]

class EnrollmentSerializer(serializers.ModelSerializer):
    student = StudentProfileSerializer()
    course = CourseSerializer()
    class Meta:
        model = Enrollment
        fields = ["id", "student", "course", "semester"]

class AttendanceSerializer(serializers.ModelSerializer):
    student = StudentProfileSerializer(read_only=True)
    course = CourseSerializer(read_only=True)

    # write-only fields for create()
    student_id = serializers.CharField(write_only=True)
    course_code = serializers.CharField(write_only=True)

    class Meta:
        model = Attendance
        fields = ["id", "student", "course", "date", "status", "student_id", "course_code"]

    def create(self, validated_data):
        sid = validated_data.pop("student_id")
        ccode = validated_data.pop("course_code")
        student = StudentProfile.objects.get(student_id=sid)
        course = Course.objects.get(code=ccode)
        return Attendance.objects.create(student=student, course=course, **validated_data)
