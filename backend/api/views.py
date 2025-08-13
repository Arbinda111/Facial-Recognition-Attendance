from django.shortcuts import render

# Create your views here.
from rest_framework import generics, permissions
from rest_framework.response import Response
from rest_framework.views import APIView
from django.contrib.auth.models import User
from .models import StudentProfile, Attendance, Course
from .serializers import (
    UserSerializer, StudentProfileSerializer, AttendanceSerializer, CourseSerializer
)

class RegisterStudentView(APIView):
    permission_classes = [permissions.AllowAny]
    def post(self, request):
        name = request.data.get("name", "")
        username = request.data.get("username", "")
        email = request.data.get("email", "")
        password = request.data.get("password", "")
        student_id = request.data.get("student_id", "")
        phone = request.data.get("phone", "")
        address = request.data.get("address", "")

        if not all([username, email, password, student_id]):
            return Response({"detail": "Missing fields"}, status=400)

        first_name, *rest = name.split(" ", 1)
        last_name = rest[0] if rest else ""

        if User.objects.filter(username=username).exists():
            return Response({"detail": "Username taken"}, status=400)
        if StudentProfile.objects.filter(student_id=student_id).exists():
            return Response({"detail": "Student ID exists"}, status=400)

        user = User.objects.create_user(
            username=username, email=email, password=password,
            first_name=first_name, last_name=last_name
        )
        profile = StudentProfile.objects.create(
            user=user, student_id=student_id, phone=phone, address=address
        )
        return Response(StudentProfileSerializer(profile).data, status=201)

class MeView(APIView):
    permission_classes = [permissions.IsAuthenticated]
    def get(self, request):
        user = request.user
        data = UserSerializer(user).data
        profile = None
        try:
            profile = StudentProfileSerializer(user.student_profile).data
        except StudentProfile.DoesNotExist:
            pass
        return Response({"user": data, "student_profile": profile})

class AttendanceCreateView(generics.CreateAPIView):
    permission_classes = [permissions.IsAuthenticated]
    serializer_class = AttendanceSerializer

class AttendanceListView(generics.ListAPIView):
    permission_classes = [permissions.IsAuthenticated]
    serializer_class = AttendanceSerializer
    def get_queryset(self):
        qs = Attendance.objects.select_related("student__user", "course").all()
        sid = self.request.query_params.get("student_id")
        if sid:
            qs = qs.filter(student__student_id=sid)
        code = self.request.query_params.get("course_code")
        if code:
            qs = qs.filter(course__code=code)
        return qs.order_by("-date")

class CourseListView(generics.ListAPIView):
    permission_classes = [permissions.IsAuthenticated]
    serializer_class = CourseSerializer
    queryset = Course.objects.all().order_by("code")
