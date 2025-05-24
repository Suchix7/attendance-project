import cv2
import sys
import json
import logging
import os

# Configure logging
logging.basicConfig(level=logging.INFO)

def detect_faces(image_path):
    try:
        # Read the image
        img = cv2.imread(image_path)
        if img is None:
            return {'success': False, 'message': 'Failed to load image'}
            
        # Convert to grayscale
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        gray = cv2.equalizeHist(gray)
        
        # Load Haar Cascade classifier
        cascade_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'assets', 'haarcascade_frontalface_default.xml')
        face_cascade = cv2.CascadeClassifier(cascade_path)
        if face_cascade.empty():
            return {'success': False, 'message': f'Failed to load face cascade classifier from {cascade_path}'}

        # Detect faces
        faces = face_cascade.detectMultiScale(
            gray,
            scaleFactor=1.05,
            minNeighbors=3,
            minSize=(30, 30),
            maxSize=(300, 300)
        )
        
        # Convert faces to list of dictionaries for JSON serialization
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
            'faces': faces_list,
            'message': f'Found {len(faces_list)} faces'
        }
        
    except Exception as e:
        logging.error(f"Error in face detection: {str(e)}")
        return {'success': False, 'message': str(e)}

if __name__ == '__main__':
    if len(sys.argv) != 2:
        print(json.dumps({'success': False, 'message': 'Image path not provided'}))
        sys.exit(1)
        
    image_path = sys.argv[1]
    result = detect_faces(image_path)
    print(json.dumps(result)) 