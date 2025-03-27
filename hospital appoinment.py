from flask import Flask, render_template, request, redirect, url_for, jsonify
import sqlite3

app = Flask(__name__)

# Database Setup
def init_db():
    with sqlite3.connect("hospital.db") as conn:
        cursor = conn.cursor()
        cursor.execute('''CREATE TABLE IF NOT EXISTS doctors (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            name TEXT NOT NULL,
                            specialization TEXT NOT NULL,
                            available INTEGER DEFAULT 1)''')
        
        cursor.execute('''CREATE TABLE IF NOT EXISTS patients (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            name TEXT NOT NULL,
                            condition TEXT)''')
        
        cursor.execute('''CREATE TABLE IF NOT EXISTS appointments (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            doctor_id INTEGER,
                            patient_id INTEGER,
                            status TEXT DEFAULT 'Waiting',
                            FOREIGN KEY (doctor_id) REFERENCES doctors(id),
                            FOREIGN KEY (patient_id) REFERENCES patients(id))''')
        conn.commit()

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/doctors')
def get_doctors():
    with sqlite3.connect("hospital.db") as conn:
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM doctors WHERE available=1")
        doctors = cursor.fetchall()
    return jsonify(doctors)

@app.route('/add_patient', methods=['POST'])
def add_patient():
    name = request.form['name']
    condition = request.form['condition']
    with sqlite3.connect("hospital.db") as conn:
        cursor = conn.cursor()
        cursor.execute("INSERT INTO patients (name, condition) VALUES (?, ?)", (name, condition))
        patient_id = cursor.lastrowid
        cursor.execute("SELECT id FROM doctors WHERE available=1 LIMIT 1")
        doctor = cursor.fetchone()
        if doctor:
            cursor.execute("INSERT INTO appointments (doctor_id, patient_id, status) VALUES (?, ?, 'In Progress')", (doctor[0], patient_id))
            cursor.execute("UPDATE doctors SET available=0 WHERE id=?", (doctor[0],))
        conn.commit()
    return redirect(url_for('index'))

@app.route('/complete_appointment/<int:appointment_id>')
def complete_appointment(appointment_id):
    with sqlite3.connect("hospital.db") as conn:
        cursor = conn.cursor()
        cursor.execute("UPDATE appointments SET status='Completed' WHERE id=?", (appointment_id,))
        cursor.execute("UPDATE doctors SET available=1 WHERE id=(SELECT doctor_id FROM appointments WHERE id=?)", (appointment_id,))
        conn.commit()
    return redirect(url_for('index'))

if __name__ == '__main__':
    init_db()
    app.run(debug=True)
