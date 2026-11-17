# Talent Portal — Freelance & Services Platform

[![PHP Version](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-00758F?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![PHPMailer](https://img.shields.io/badge/PHPMailer-6.9-green?style=for-the-badge&logo=gmail&logoColor=white)](https://github.com/PHPMailer/PHPMailer)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.x-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)

A full-stack web marketplace platform connecting skilled workers, freelancers, and technical professionals with prospective clients. The application features dual-role dashboards, portfolio showcasing, an end-to-end recruitment tracking pipeline, ratings and reviews, and mutual-connection messaging.

---

## 🌟 Key Features

### 🛠️ Worker & Freelancer Dashboard (`worker/`)
- **Portfolio Showcase**: Upload and manage project portfolios, visual samples, and work proofs.
- **Skill Progress Tracker**: Record and visualize technical competency levels across multiple skill categories (`worker_skills`).
- **Job Discovery & Applications**: Browse posted job openings, submit proposals, and monitor application statuses in real time.

### 🏢 Client Portal (`client/`)
- **Job Requisition Management**: Create, edit, and publish job listings with compensation, requirements, and deadlines (`jobs`).
- **Multi-Stage Hiring Pipeline**: Manage applicant progression through structured stages: **Pending**, **Shortlisted**, **Interview**, and **Hired** (`applications`).
- **Ratings & Client Reviews**: Provide transparent ratings and qualitative feedback upon project completion (`ratings`).

### 💬 Networking & Collaboration
- **Mutual-Connection Chat**: Real-time direct messaging between connected workers and hiring clients (`messages`, `connections`).
- **Social Feed & Interaction**: Share professional updates with likes, comments, and views (`posts`, `post_likes`, `post_comments`).
- **OTP Account Recovery**: Two-step email verification via PHPMailer for secure password resets (`forgot_password.php`, `verify_otp.php`).

---

## 💻 Tech Stack

- **Backend**: PHP 8.x
- **Database**: MySQL (MariaDB, UTF8MB4)
- **Frontend**: HTML5, CSS3, JavaScript (ES6+), Bootstrap
- **Email Service**: PHPMailer (SMTP-based automated OTP verification)
- **Server**: Apache / XAMPP

---

## 🗄️ Relational Database Architecture

The system operates on an 11-table relational database schema (`database.sql`):

| Table | Description |
| :--- | :--- |
| `users` | Accounts, profiles, contact info, and role flags (`worker`, `client`). |
| `jobs` | Job postings, budgets, category tags, and hiring deadlines. |
| `applications` | Candidate proposals, cover letters, and application stages. |
| `connections` | Professional connection requests and accepted network links. |
| `messages` | Direct chat records between connected clients and workers. |
| `ratings` | Performance ratings, star scores, and qualitative client reviews. |
| `worker_skills` | Skill proficiency metrics and progress levels per worker. |
| `posts` / `post_likes` | Community updates, portfolio showcase posts, and engagement. |
| `notifications` | User activity alerts for applications, messages, and hires. |

---

## ⚙️ Local Setup & Installation

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (PHP 8.0+ and MySQL)
- [Composer](https://getcomposer.org/)

### 1. Clone the Repository
```bash
git clone https://github.com/deepika-v2005/Talent-Portal.git
cd Talent-Portal
```

### 2. Configure Database
1. Launch **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Create a database named `skillconnect`:
   ```sql
   CREATE DATABASE skillconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import the `database.sql` file into the `skillconnect` database.

### 3. Install Dependencies
```bash
composer install
```

### 4. Access the Application
Place the directory inside your web server root (`C:/xampp/htdocs/job_portal/`) and open:
- `http://localhost/job_portal/index.php`

---

## 👤 Author

**Deepika V**  
- GitHub: [@deepika-v2005](https://github.com/deepika-v2005)  
- LinkedIn: [Deepika V](https://linkedin.com/in/deepika-v-a00974316)
