import cv2
import numpy as np
import os
import sys
import json

def detect_faces(image_path):
    """Simple face detection using Haar Cascade"""
    try:
        # Get the cascade file path
        base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        cascade_path = os.path.join(base_dir, 'assets/haarcascade_frontalface_default.xml')
        
        # Load the cascade
        face_cascade = cv2.CascadeClassifier(cascade_path)
        if face_cascade.empty():
            return {
                'success': False,
                'face_detected': False,
                'message': 'Failed to load face detection model'
            }
        
        # Read the image
        img = cv2.imread(image_path)
        if img is None:
            return {
                'success': False,
                'face_detected': False,
                'message': 'Failed to load image'
            }
        
        # Convert to grayscale
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        
        # Detect faces
        faces = face_cascade.detectMultiScale(
            gray,
            scaleFactor=1.1,
            minNeighbors=3,
            minSize=(30, 30),
            maxSize=(300, 300)
        )
        
        if len(faces) == 0:
            return {
                'success': False,
                'face_detected': False,
                'message': 'No face detected. Please look directly at the camera.'
            }
        
        # Get the largest face
        largest_face = max(faces, key=lambda rect: rect[2] * rect[3])
        x, y, w, h = largest_face
        
        return {
            'success': True,
            'face_detected': True,
            'message': 'Face detected successfully',
            'face_location': {
                'x': int(x),
                'y': int(y),
                'width': int(w),
                'height': int(h)
            }
        }
        
    except Exception as e:
        return {
            'success': False,
            'face_detected': False,
            'message': str(e)
        }

if __name__ == '__main__':
    if len(sys.argv) != 2:
        result = {
            'success': False,
            'face_detected': False,
            'message': 'Please provide an image path'
        }
    else:
        result = detect_faces(sys.argv[1])
    
    # Ensure proper JSON output
    print(json.dumps(result)) 