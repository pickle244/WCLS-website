--create families table
CREATE TABLE IF NOT EXISTS families
(
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  user_id INT(11),
  FOREIGN KEY (user_id) REFERENCES users(id),
  relationship VARCHAR(255),
  mobile_number INT(10),
  home_address VARCHAR(255),
  home_city VARCHAR(255),
  home_state VARCHAR(255),
  home_zip INT(5),
  emergency_contact_name VARCHAR(128),
  emergency_contact_number INT(10),
  registration_due TIMESTAMP,
  registration_payment FLOAT,
  created_at TIMESTAMP DEFAULT current_timestamp
)

--create students table
CREATE TABLE IF NOT EXISTS students
(
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  family_id INT(11),
  FOREIGN KEY (family_id) REFERENCES families(id),
  first_name VARCHAR(128),
  last_name VARCHAR(128),
  DOB DATE
  created_at TIMESTAMP DEFAULT current_timestamp
)

--create enrollments table
CREATE TABLE IF NOT EXISTS enrollments
(
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  student_id INT(11),
  FOREIGN KEY (student_id) REFERENCES students(id),
  FOREIGN KEY (course_id) REFERENCES courses(id),
  payment_status VARCHAR(255),
  created_at TIMESTAMP DEFAULT current_timestamp
)

--create orders table
CREATE TABLE IF NOT EXISTS orders
(
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  user_id INT(11),
  FOREIGN KEY (user_id) REFERENCES users(id),
  order_date DATETIME DEFAULT current_timestamp,
  order_total FLOAT,
  order_status VARCHAR(255)
)

--create order_lines table
CREATE TABLE IF NOT EXISTS order_lines
(
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  FOREIGN KEY (course_id) REFERENCES courses(id),
  FOREIGN KEY (order_id) REFERENCES orders(id),
  qty INT
  price FLOAT
  created_at datetime not null default current_timestamp,
)

-- carts
-- shopping cart
CREATE TABLE IF NOT EXISTS carts(
    cart_id INT(11) auto_increment primary key,   -- primary key
    user_id int(11) ,
    created_at datetime not null default current_timestamp,

    FOREIGN KEY (user_id) references users(id)
      
)

-- cart_items
create table if not exists cart_items (
    id int(11) auto_increment primary key,
    cart_id int(11),
    student_id int(11),
    course_id int(11),
    created_at datetime not null default current_timestamp,

    FOREIGN KEY (cart_id) references carts(id),
    FOREIGN KEY (course_id) references courses(id),
    FOREIGN KEY (student_id) references students(id)
)