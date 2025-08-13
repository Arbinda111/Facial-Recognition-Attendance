from django.contrib import admin
from django.urls import path
from rest_framework_simplejwt.views import TokenObtainPairView, TokenRefreshView
from api.views import (
    RegisterStudentView, MeView,
    AttendanceCreateView, AttendanceListView, CourseListView,
)

urlpatterns = [
    path("admin/", admin.site.urls),

    # JWT
    path("api/token/", TokenObtainPairView.as_view(), name="token_obtain_pair"),
    path("api/token/refresh/", TokenRefreshView.as_view(), name="token_refresh"),

    # Core API
    path("api/me/", MeView.as_view()),
    path("api/register/", RegisterStudentView.as_view()),
    path("api/attendance/", AttendanceListView.as_view()),
    path("api/attendance/create/", AttendanceCreateView.as_view()),
    path("api/courses/", CourseListView.as_view()),
]
