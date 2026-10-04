CREATE TABLE users (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  email TEXT NOT NULL,
  password TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'student'
);

CREATE TABLE tutor_profiles (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL UNIQUE,
  headline TEXT,
  bio TEXT,
  hourly_rate REAL,
  rating_avg REAL,
  reviews_count INTEGER,
  city TEXT
);

CREATE TABLE bookings (
  id INTEGER PRIMARY KEY,
  tutor_id INTEGER,
  student_id INTEGER,
  starts_at TEXT,
  ends_at TEXT,
  status TEXT,
  total_price REAL
);
