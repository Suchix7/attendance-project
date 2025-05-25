import cv2
import numpy as np
import os
import sys
import json
import logging

# Configure logging
logging.basicConfig(level=logging.INFO)

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
                'message': 'Failed to load face detection model'
            }
        
        # Read the image
        img = cv2.imread(image_path)
        if img is None:
            return {
                'success': False,
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
                'message': 'No face detected'
            }
        
        # Convert faces to list of dictionaries
        faces_list = []
        for (x, y, w, h) in faces:
            faces_list.append({
                'x': int(x),
                'y': int(y),
                'width': int(w),
                'height': int(h)
            })
        
        return {
            'success': True,
            'message': 'Face(s) detected successfully',
            'faces': faces_list
        }
        
    except Exception as e:
        logging.error(f"Error in face detection: {str(e)}")
        return {
            'success': False,
            'message': str(e)
        }

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({
            'success': False,
            'message': 'No image path provided'
        }))
        sys.exit(1)
        
    result = detect_faces(sys.argv[1])
    print(json.dumps(result)) 