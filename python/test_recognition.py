import sys
import os
from recognize_face import recognize_face

def test_recognition():
    # Get the path to a training image
    student_id = "13"
    image_path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), f'students/{student_id}/face_1.jpg')
    
    if not os.path.exists(image_path):
        print(f"Test image not found at {image_path}")
        return
        
    print(f"Testing recognition with image: {image_path}")
    result = recognize_face(image_path)
    print("\nRecognition Result:")
    print("-" * 50)
    for key, value in result.items():
        print(f"{key}: {value}")

if __name__ == "__main__":
    test_recognition() 