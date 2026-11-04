# Planora

### Plan your day. Shape your future.

**Planora** is a personal task management web application designed to help users organize their daily activities, manage priorities, track progress, and achieve their goals more effectively.

The project is being developed as a full-stack web application using Laravel and Vue.js, with a focus on clean architecture, maintainable code, and a modern user experience.

> **Project Status:** 🚧 In Development

---

## Overview

Planora aims to provide a centralized workspace where users can organize their tasks and manage different areas of their daily lives.

The application is planned to include task management, category organization, scheduling, progress tracking, and personal productivity tools.

## Planned Features

- **Dashboard** – Overview of tasks, productivity, and progress.
- **Task Management** – Create, edit, delete, and organize tasks.
- **Categories** – Organize tasks into Work, Education, and Personal categories.
- **Priority Management** – Assign different priority levels to tasks.
- **Calendar** – Schedule tasks and manage deadlines.
- **Statistics** – Monitor task completion and productivity.
- **Settings** – Manage personal application preferences.

*Features are planned and will be implemented progressively.*

## Technology Stack

| Technology | Purpose |
|---|---|
| PHP 8.1 | Backend language |
| Laravel 10 | Backend framework |
| Vue.js 3 | Frontend framework |
| Vite | Frontend build tool |
| MySQL | Database |
| JavaScript | Frontend programming |
| HTML5 & CSS3 | Structure and styling |
| Git & GitHub | Version control |

## Project Structure

The project follows a Laravel-based application structure with Vue.js integration.

```text
Planora/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
│   ├── css/
│   ├── js/
│   └── views/
├── routes/
├── storage/
├── tests/
├── package.json
├── composer.json
└── vite.config.js
```

The frontend and backend structure will evolve as development progresses.

## Getting Started

### Prerequisites

Make sure you have the following installed:

- PHP 8.1 or compatible version
- Composer
- Node.js and npm
- MySQL
- Git

### Installation

**1. Clone the repository**

```bash
git clone https://github.com/ElmirVelizadeDev/Planora.git
```

**2. Navigate to the project directory**

```bash
cd Planora
```

**3. Install PHP dependencies**

```bash
composer install
```

**4. Install JavaScript dependencies**

```bash
npm install
```

**5. Configure environment variables**

```bash
cp .env.example .env
```

On Windows PowerShell, you can use:

```powershell
Copy-Item .env.example .env
```

**6. Generate the application key**

```bash
php artisan key:generate
```

**7. Configure your database**

Create a MySQL database and update the database settings in your `.env` file.

**8. Run database migrations**

```bash
php artisan migrate
```

**9. Start the development environment**

In one terminal:

```bash
php artisan serve
```

In another terminal:

```bash
npm run dev
```

The application will be available at:

`http://127.0.0.1:8000`

## Development Roadmap

- [x] Initialize Laravel project
- [x] Install Vue.js 3
- [x] Configure Vite Vue plugin
- [x] Initialize Git repository
- [x] Create GitHub repository
- [ ] Complete Laravel and Vue.js integration
- [ ] Establish project architecture
- [ ] Configure database and application models
- [ ] Develop authentication system
- [ ] Build dashboard interface
- [ ] Implement task management functionality
- [ ] Develop calendar and statistics modules
- [ ] Implement application settings
- [ ] Testing and optimization

## Project Goals

Planora is also a learning-oriented software engineering project focused on:

- Object-Oriented Programming (OOP)
- MVC architecture
- SOLID principles
- RESTful API development
- Database design and relationships
- Authentication and authorization
- Web application security
- Clean code and software architecture
- Git and collaborative development workflows

## Author

**Elmir Velizade**

- GitHub: [@ElmirVelizadeDev](https://github.com/ElmirVelizadeDev)

---

**Planora — Plan your day. Shape your future.**
