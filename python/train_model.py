import cv2
import numpy as np
import os
import sys
import json
import pickle
import logging
from improved_face_recognition import train_improved_lbph

# Set up logging
logging.basicConfig(filename='face_recognition.log', level=logging.DEBUG)

def train_lbph_model(student_id):
    """Train LBPH model for a new student using improved implementation"""
    try:
        # Get absolute paths
        base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        student_dir = os.path.join(base_dir, 'students', student_id)
        info_file = os.path.join(student_dir, 'info.json')
        
        if not os.path.exists(info_file):
            logging.error(f"Student info not found at {info_file}")
            return {'success': False, 'message': f'Student info not found at {info_file}'}
        
        # Train the model using improved implementation
        result = train_improved_lbph()
        
        if result['success']:
            logging.info("Model training completed successfully")
            return {
                'success': True,
                'message': f"Model trained successfully with {result['num_faces']} faces from {result['num_students']} students"
            }
        else:
            logging.error(f"Training failed: {result['message']}")
            return result
            
    except Exception as e:
        logging.error(f"Unexpected error: {str(e)}")
        return {'success': False, 'message': str(e)}

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(json.dumps({'success': False, 'message': 'Invalid arguments'}))
        sys.exit(1)

    student_id = sys.argv[1]
    result = train_lbph_model(student_id)
    print(json.dumps(result, indent=2)) 