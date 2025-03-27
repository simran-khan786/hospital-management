from flask import Flask, render_template, request, redirect, url_for, jsonify
import mysql.connector

app = Flask(__name__)

# Database Configuration
db_config = {
    'host': 'localhost',
    'user': 'your_username',  # Change this
    'password': 'your_password',  # Change this
    'database': 'hospital_management'
}

# Connect to MySQL
def connect_db():
    return mysql.connector.connect(**db_config)

# Home Page (Register Patients)
@app.route('/')
def index():
    return render_template('index.html')

# Register New Patient
@app.route('/register_patient', methods=['POST'])
def register_patient():
    name = request.form['name']
    condition = request.form['condition']
    
    conn = connect_db()
    cursor = conn.cursor()
    cursor.execute("INSERT INTO patients (name, condition, check_in_time) VALUES (%s, %s, NOW())", (name, condition))
    conn.commit()
    
    cursor.close()
    conn.close()
    return redirect(url_for('live_queue'))

# Assign Doctor Dynamically
@app.route('/assign_doctor')
def assign_doctor():
    conn = connect_db()
    cursor = conn.cursor()

    # Find the next waiting patient
    cursor.execute("SELECT id FROM patients ORDER BY check_in_time ASC LIMIT 1")
    patient = cursor.fetchone()
    if not patient:
        return "No patients in queue!", 400
    patient_id = patient[0]

    # Find an available doctor
    cursor.execute("SELECT id FROM doctors WHERE availability = TRUE ORDER BY id ASC LIMIT 1")
    doctor = cursor.fetchone()
    if not doctor:
        return "No available doctors!", 400
    doctor_id = doctor[0]

    # Assign appointment
    cursor.execute("INSERT INTO appointments (doctor_id, patient_id, status, appointment_time) VALUES (%s, %s, 'In Progress', NOW())", (doctor_id, patient_id))
    cursor.execute("UPDATE doctors SET availability = FALSE WHERE id = %s", (doctor_id,))
    
    conn.commit()
    cursor.close()
    conn.close()
    
    return redirect(url_for('live_queue'))

# Live Queue Management
@app.route('/live_queue')
def live_queue():
    conn = connect_db()
    cursor = conn.cursor(dictionary=True)
    
    cursor.execute("""
        SELECT p.name AS patient_name, d.name AS doctor_name, a.status
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        ORDER BY a.appointment_time ASC
    """)
    
    queue_data = cursor.fetchall()
    cursor.close()
    conn.close()
    
    return render_template('queue.html', queue=queue_data)

# Complete Appointment
@app.route('/complete_appointment/<int:appointment_id>')
def complete_appointment(appointment_id):
    conn = connect_db()
    cursor = conn.cursor()
    
    # Mark appointment as completed
    cursor.execute("UPDATE appointments SET status = 'Completed' WHERE id = %s", (appointment_id,))
    
    # Make doctor available again
    cursor.execute("UPDATE doctors SET availability = TRUE WHERE id = (SELECT doctor_id FROM appointments WHERE id = %s)", (appointment_id,))
    
    conn.commit()
    cursor.close()
    conn.close()
    
    return redirect(url_for('live_queue'))

if __name__ == '__main__':
    app.run(debug=True)
