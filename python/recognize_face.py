import cv2
import numpy as np
import os
import sys
import json
import pickle
from improved_face_recognition import recognize_face_improved

def recognize_face(image_path):
    """Face recognition using improved LBPH implementation"""
    return recognize_face_improved(image_path)

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(json.dumps({
            'success': False,
            'message': 'Please provide an image path'
        }))
        sys.exit(1)
        
    result = recognize_face(sys.argv[1])
    print(json.dumps(result, indent=2)) 