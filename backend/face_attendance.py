import face_recognition
import cv2
import os
import django
import datetime

# Setup Django environment
os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'fullattend.settings')
django.setup()

from attendance.models import Student, Attendance

# Folder containing face images
IMAGE_DIR = os.path.join(os.getcwd(), 'student_images')

print("📁 Loading student images...")

# Load known faces
known_encodings = []
known_students = []

for student in Student.objects.all():
    image_filename = os.path.basename(student.image_path).strip()
    image_path = os.path.join(IMAGE_DIR, image_filename)

    if os.path.exists(image_path):
        image = face_recognition.load_image_file(image_path)
        encoding = face_recognition.face_encodings(image)
        if encoding:
            known_encodings.append(encoding[0])
            known_students.append(student)
        else:
            print(f"⚠️ No face found in image: {image_filename}")
    else:
        print(f"⚠️ Image not found: {image_path}")

# Open webcam
video_capture = cv2.VideoCapture(0)
if not video_capture.isOpened():
    print("❌ Could not open webcam.")
    exit()

print("🎥 Webcam started - Scanning for faces (press Q to quit)...")

while True:
    ret, frame = video_capture.read()
    if not ret or frame is None:
        print("❌ Failed to read frame from webcam.")
        continue

    # Resize and convert to RGB
    small_frame = cv2.resize(frame, (0, 0), fx=0.25, fy=0.25)
    rgb_small_frame = cv2.cvtColor(small_frame, cv2.COLOR_BGR2RGB)

    # Detect faces and compare
    face_locations = face_recognition.face_locations(rgb_small_frame)
    face_encodings = face_recognition.face_encodings(rgb_small_frame, face_locations)

    for face_encoding in face_encodings:
        matches = face_recognition.compare_faces(known_encodings, face_encoding)
        if True in matches:
            match_index = matches.index(True)
            student = known_students[match_index]
            today = datetime.date.today()

            already_marked = Attendance.objects.filter(student=student, date=today).exists()
            if not already_marked:
                Attendance.objects.create(student=student, status="Present")
                print(f"✅ {student.name} marked Present")
            else:
                print(f"🕒 {student.name} already marked today")

    # Show camera frame
    cv2.imshow('Face Attendance - Press Q to Quit', frame)

    if cv2.waitKey(1) & 0xFF == ord('q'):
        break

video_capture.release()
cv2.destroyAllWindows()
print("👋 Face attendance closed.")
