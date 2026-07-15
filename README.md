# Eden Health - Project Context & Developer Reference

This document serves as a high-density, token-efficient technical overview and operational runbook optimized for AI Agents and Human Developers.

## 🚀 Quick Specs
- **Category:** IT Graduation Project (Đồ án Công nghệ Thông tin) - Healthcare & Appointment Management System.
- **Frontend Environment:** Pure HTML/CSS/Vanilla JS. Served via VS Code **Live Server** (`http://localhost:5500`).
- **Backend Environment:** PHP (REST APIs). Runs on **XAMPP / Apache** (`http://localhost/DO_AN/src/`).
- **Database:** MySQL. Managed via **PhpMyAdmin** (`http://localhost/phpmyadmin`).
- **Local LLM Stack:** AI Chatbot & Specialty Recommendation (`api/patient/ai-suggest-specialty.php`).
  - **Runner:** Ollama (Port `11434`) & AnythingLLM (Port `3001`).
  - **Models:** `qwen2.5:3b`, `qwen3:4b`.
  - **AnythingLLM Workspace:** `test`
- **External Services:**
  - **Transactional Mail:** Brevo API (SMTP/API integration for registrations, OTPs, reminders, and payment notifications).
  - **Cloud Media Storage:** Cloudinary (secure image uploads for doctor avatars and user profiles).
  - **Payment Gateway:** VNPAY.
- **Production Deployment (Tested):**
  - **Domain:** `domainex.id.vn`
  - **Hosting:** 123host Free Shared Hosting.

---

## 📂 Codebase Directory Map

```text
src/
├── index.html                      # Landing & entrypoint
├── *.html                          # Module-specific frontend views (Admin, Doctor, Patient dashboards)
├── assets/                         # CSS stylesheets & client-side JS logic
├── components/                     # Reusable HTML snippets (headers, footers, sidebars)
├── config/                         # Core system configurations
│   ├── app-env.php                 # Global app environment & constants
│   ├── db-config.php / db.php      # Database connection & credentials
│   ├── mail.php / mail-notifications.php # SMTP/Brevo config
│   ├── cloudinary.php              # Cloudinary API settings
│   └── cors.php                    # CORS headers for API safety
├── api/                            # Backend REST APIs (group-based routing)
│   ├── auth/                       # Login, registration, OTP, password resets
│   ├── patient/                    # Patient actions (booking, AI triage, medical records)
│   ├── doctor/                     # Medical records update, queue management, leaves
│   ├── admin/                      # CRUDs, statistics dashboards, configs, file exports
│   └── payment/                    # VNPAY return handler
├── includes/                       # Core helper utilities & transaction event handlers
│   ├── send-mail.php               # Brevo SMTP wrapper
│   ├── cloudinary-upload.php       # Cloudinary SDK wrapper
│   └── schedule-management.php    # Appointment slot generators
├── cron/                           # Background schedulers
│   ├── auto-cancel-expired-appointments.php
│   └── send-appointment-reminders.php
└── database/                       # SQL migrations & seeds
    ├── db.sql                      # Primary schema and seed data
    ├── db_thuoc.sql                # Medicine/pharmaceutical seed data
    └── db_host.sql                 # Production-specific host database
```

---

## 👥 System Actors & Roles
1. **Patient (Bệnh nhân):** Authenticates (OTP, email), books appointment slots, views personal medical records/history, receives Brevo email notices/reminders, asks AI for medical specialty recommendations.
2. **Doctor (Bác sĩ):** Manages today's queue, updates diagnoses, prescribes medicine (linked to stock), submits leave requests.
3. **Administrator (Quản trị viên):** Manages departments, doctor schedules, patient accounts, pharmaceutical stock, generates interactive PDF/Excel/CSV reports.

---

## ⚙️ Local Setup Guide

### 1. Database Setup (MySQL)
- Start MySQL in XAMPP.
- Access PhpMyAdmin -> Create a database (e.g. `eden_health`).
- Import `database/db.sql` and `database/db_thuoc.sql` to populate initial schemas, master lists, and sample datasets.

### 2. Backend Setup (XAMPP / PHP)
- Place project inside `C:\xampp\htdocs\DO_AN\src`.
- Ensure PHP version is `8.x` or compatible with modern MySQLi.
- Configure SMTP/Brevo credentials and Cloudinary credentials in `config/` files.

### 3. Frontend Setup (Live Server)
- Open the workspace in VS Code.
- Launch `index.html` via **Live Server** (defaults to `http://127.0.0.1:5500` or `http://localhost:5500`).
- Ensure CORS in `config/cors.php` allows requests from port `5500`.

### 4. AI & LLM Integration Setup
- **Ollama:** Install Ollama, fetch models:
  ```bash
  ollama run qwen2.5:3b
  ollama run qwen3:4b
  ```
- **AnythingLLM:** Setup AnythingLLM on port `3001` with workspace `test`.
- The PHP backend makes HTTP POST requests to `http://localhost:3001` via `api/patient/ai-suggest-specialty.php` using the AnythingLLM API Key.

---

## ⚡ Key Workflows & API Paradigms
- **API Payloads:** Strictly `application/json` for requests & responses.
- **State Management:** Session-based PHP authentication (`config/session.php`).
- **AI Recommendation Flow:**
  1. Patient enters description of symptoms.
  2. Frontend POSTs payload to `api/patient/ai-suggest-specialty.php`.
  3. API fetches available Specialties from DB to append to prompt.
  4. API routes request to Local LLM (AnythingLLM API on port 3001).
  5. JSON formatted specialties, confidence ratings, and reasoning are returned to the client.
- **Stock Management:** Medicine inventory is atomically updated when a Doctor completes a prescription record (`includes/medicine-stock.php`).
