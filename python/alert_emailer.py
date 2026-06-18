import os
import sys
import json
import smtplib
from email.mime.text import MIMEText
from email.header import Header

# Path to email configuration
CONFIG_PATH = os.path.join(os.path.dirname(__file__), 'email_config.json')
LOG_PATH = os.path.join(os.path.dirname(__file__), '..', 'temp', 'sent_emails.log')

def log_email_locally(to_email, subject, body, error_msg=None):
    """Fallback to logging the email to a local file."""
    os.makedirs(os.path.dirname(LOG_PATH), exist_ok=True)
    with open(LOG_PATH, 'a', encoding='utf-8') as f:
        f.write("="*60 + "\n")
        if error_msg:
            f.write(f"STATUS: FAILED (SMTP error: {error_msg}) - LOGGED LOCALLY\n")
        else:
            f.write("STATUS: LOGGED LOCALLY (No SMTP config)\n")
        f.write(f"TO: {to_email}\n")
        f.write(f"SUBJECT: {subject}\n")
        f.write("-" * 40 + "\n")
        f.write(body + "\n")
        f.write("="*60 + "\n\n")
    print(json.dumps({"success": True, "logged_locally": True, "message": "Email logged to temp/sent_emails.log"}))

def main():
    try:
        # Read JSON input from stdin
        input_data = sys.stdin.read()
        if not input_data.strip():
            print(json.dumps({"success": False, "message": "No input received"}))
            return

        data = json.loads(input_data)
        to_email = data.get('to')
        subject = data.get('subject')
        body = data.get('body')

        if not to_email or not subject or not body:
            print(json.dumps({"success": False, "message": "to, subject, and body are required fields"}))
            return

        # Check SMTP configuration
        smtp_config = {}
        if os.path.exists(CONFIG_PATH):
            try:
                with open(CONFIG_PATH, 'r') as f:
                    smtp_config = json.load(f)
            except Exception as e:
                pass

        host = smtp_config.get('host')
        port = smtp_config.get('port')
        secure = smtp_config.get('secure', False)
        username = smtp_config.get('username')
        password = smtp_config.get('password')

        # If SMTP config is missing, log locally
        if not host or not username or not password:
            log_email_locally(to_email, subject, body)
            return

        # Prepare MIME Message
        msg = MIMEText(body, 'plain', 'utf-8')
        msg['Subject'] = Header(subject, 'utf-8')
        msg['From'] = username
        msg['To'] = to_email

        # Connect and send
        try:
            if secure:
                server = smtplib.SMTP_SSL(host, port, timeout=10)
            else:
                server = smtplib.SMTP(host, port, timeout=10)
                server.starttls()
            
            server.login(username, password)
            server.sendmail(username, [to_email], msg.as_string())
            server.quit()
            
            print(json.dumps({"success": True, "logged_locally": False, "message": "Email sent successfully via SMTP"}))
        except Exception as smtp_err:
            # Fallback to local logging on SMTP error
            log_email_locally(to_email, subject, body, error_msg=str(smtp_err))

    except Exception as e:
        print(json.dumps({"success": False, "message": f"Global script error: {str(e)}"}))

if __name__ == '__main__':
    main()
