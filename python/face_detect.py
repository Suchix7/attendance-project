import cv2
import numpy as np
import os
import sys
import json

def detect_faces(image_path):
    try:
        # Load the image
        image = cv2.imread(image_path)
        if image is None:
            return {'success': False, 'message': 'Failed to load image'}

        # Convert to grayscale
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)

        # Apply histogram equalization
        gray = cv2.equalizeHist(gray)

        # Load face detection cascade
        face_cascade = cv2.CascadeClassifier('haarcascade_frontalface_default.xml')
        if face_cascade.empty():
            return {'success': False, 'message': 'Failed to load face detection model'}

        # Detect faces with optimized parameters
        faces = face_cascade.detectMultiScale(
            gray,
            scaleFactor=1.1,  # Reduced from 1.05 for better detection
            minNeighbors=4,   # Increased from 3 for better accuracy
            minSize=(30, 30),
            maxSize=(400, 400)  # Increased max size
        )

        if len(faces) == 0:
            return {'success': False, 'message': 'No face detected'}

        # Process detected faces
        processed_faces = []
        for (x, y, w, h) in faces:
            # Calculate face center
            center_x = x + w // 2
            center_y = y + h // 2
            
            # Calculate face size relative to image
            face_size = min(w, h)
            image_size = min(image.shape[0], image.shape[1])
            size_ratio = face_size / image_size
            
            # Add face information
            processed_faces.append({
                'x': int(x),
                'y': int(y),
                'width': int(w),
                'height': int(h),
                'center_x': int(center_x),
                'center_y': int(center_y),
                'size_ratio': float(size_ratio)
            })

        return {
            'success': True,
            'faces': processed_faces
        }

    except Exception as e:
        return {'success': False, 'message': str(e)}

if __name__ == '__main__':
    if len(sys.argv) != 2:
        print(json.dumps({'success': False, 'message': 'Invalid arguments'}))
        sys.exit(1)

    image_path = sys.argv[1]
    result = detect_faces(image_path)
    print(json.dumps(result)) 