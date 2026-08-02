import os
import sys
import json
import logging

# Set up logging
logging.basicConfig(level=logging.DEBUG)

def train_lbph_model(student_id):
    """Train all face recognition models (LBPH, Eigenfaces, Fisherfaces) for all students."""
    try:
        # Import the correct training function from realtime_recognition
        # (this is the only training pipeline; improved_face_recognition no longer exists)
        script_dir = os.path.dirname(os.path.abspath(__file__))
        if script_dir not in sys.path:
            sys.path.insert(0, script_dir)

        from realtime_recognition import train_models

        success, message = train_models()

        if success:
            logging.info("Model training completed successfully")
            return {
                'success': True,
                'message': message
            }
        else:
            logging.error(f"Training failed: {message}")
            return {'success': False, 'message': message}

    except Exception as e:
        logging.error(f"Unexpected error: {str(e)}")
        return {'success': False, 'message': str(e)}


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({'success': False, 'message': 'Usage: train_model.py <student_id>'}))
        sys.exit(1)

    student_id = sys.argv[1]
    result = train_lbph_model(student_id)
    print(json.dumps(result, indent=2))