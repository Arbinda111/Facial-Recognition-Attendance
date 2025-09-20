from django.urls import path
from . import views

urlpatterns = [
    path('register/', views.RegisterStudentView.as_view(), name='register'),
    path('register-multi-angle/', views.MultiAngleRegisterView.as_view(), name='register_multi_angle'),
    path('mark-attendance/', views.AttendanceView.as_view(), name='attendance'),
    path('train-model/', views.TrainModelView.as_view(), name='train_model'),
    path('model-stats/', views.ModelStatsView.as_view(), name='model_stats'),
    path('batch-register/', views.BatchRegisterView.as_view(), name='batch_register'),
    path('test-face-match/', views.TestFaceMatchView.as_view(), name='test_match'),
    path('students/', views.ListStudentsView.as_view(), name='students'),
    path('attendance-history/', views.AttendanceHistoryView.as_view(), name='history'),
]