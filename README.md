# Planora

### Plan your day. Shape your future.

**Planora** is a personal task management and productivity web application designed to help users organize daily activities, manage priorities, track progress, and achieve their goals more effectively.

The project is being developed as a full-stack application using **Laravel and Vue.js**, with an emphasis on clean architecture, maintainable code, application security, and a modern user experience.

> **Project Status:** 🚧 In Development

---

## Overview

Planora aims to provide users with a centralized workspace for managing tasks, organizing activities, planning schedules, and monitoring personal productivity.

The application follows a **Laravel REST API + Vue SPA architecture**, separating backend responsibilities from frontend presentation and navigation.

The project includes separate User and Admin panel structures, with shared frontend design principles and distinct functionality.

## Planned Features

- **Dashboard** – Overview of tasks, productivity, and progress.
- **Task Management** – Create, edit, delete, and organize tasks.
- **Projects** – Group related tasks into projects.
- **Categories** – Organize tasks into different areas of life.
- **Priority Management** – Assign priority levels to tasks.
- **Calendar** – Schedule tasks and manage deadlines.
- **Statistics & Analytics** – Monitor task completion and productivity.
- **Notifications** – Receive relevant activity updates.
- **User Profile** – Manage personal account information.
- **Admin Panel** – Manage users and oversee application activity.
- **Settings** – Manage personal application preferences.

*Features are planned and will be implemented progressively.*

## Technology Stack

| Technology | Purpose |
|---|---|
| PHP 8.1 | Backend programming language |
| Laravel 10 | Backend framework |
| Vue.js 3 | Frontend framework |
| Vue Router | Frontend navigation |
| Pinia | Frontend state management |
| Axios | HTTP client and API communication |
| Laravel Sanctum | SPA authentication |
| Vite 5 | Frontend development and build tool |
| MySQL | Relational database |
| JavaScript | Frontend programming |
| HTML5 & CSS3 | Structure and styling |
| Git & GitHub | Version control |

## Architecture

Planora uses a **Laravel Backend + Vue.js Single Page Application (SPA)** architecture.

### Backend — Laravel

Laravel is responsible for:

- RESTful API endpoints.
- Authentication and session management.
- Authorization and access control.
- Database operations and relationships.
- Server-side validation.
- Business logic.
- Middleware and security.
- Application configuration.

API endpoints are organized under the `/api/v1` prefix.

### Frontend — Vue.js

Vue.js is responsible for:

- User interface and page rendering.
- Frontend navigation through Vue Router.
- Client-side state management with Pinia.
- API communication through Axios.
- User and Admin panel interfaces.
- Reusable components and layouts.

Vue Router uses HTML5 History mode with lazy-loaded routes.

**Security principle:** Frontend route guards improve navigation and user experience, but authorization must always be enforced on the Laravel backend.

## Project Structure

The project follows a Laravel-based structure with an integrated Vue.js SPA.

```text
Planora/
├── app/
│   ├── Http/
│   ├── Models/
│   └── Providers/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
│   ├── css/
│   ├── js/
│   │   ├── components/
│   │   ├── layouts/
│   │   ├── pages/
│   │   ├── router/
│   │   ├── services/
│   │   └── stores/
│   └── views/
├── routes/
│   ├── api.php
│   └── web.php
├── storage/
├── tests/
├── PLANORA_ARCHITECTURE.md
├── PLANORA_FRONTEND_PAGE_STRUCTURE.md
├── composer.json
├── package.json
└── vite.config.js
```

*This is a high-level overview. The actual directory structure may evolve as development progresses.*

## Documentation

The project includes dedicated documentation to keep its architecture and frontend organization clear.

| Document | Description |
|---|---|
| `PLANORA_ARCHITECTURE.md` | Backend and frontend architecture, routing, API and integration decisions |
| `PLANORA_FRONTEND_PAGE_STRUCTURE.md` | User, Admin, Authentication and Error page organization |

## Getting Started

### Prerequisites

Make sure the following tools are installed:

- PHP 8.1 or compatible version.
- Composer.
- Node.js and npm.
- MySQL.
- Git.

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

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

**6. Generate the application key**

```bash
php artisan key:generate
```

**7. Configure the database**

Create a MySQL database and configure the database connection in your `.env` file.

**8. Run database migrations**

```bash
php artisan migrate
```

**9. Start the development environment**

Run the Laravel development server:

```bash
php artisan serve
```

In a separate terminal, start the Vite development server:

```bash
npm run dev
```

The application will be available at:

`http://127.0.0.1:8000`

### Production Build

To generate optimized frontend assets:

```bash
npm run build
```

## Development Roadmap

- [x] Initialize Laravel project.
- [x] Install Vue.js 3.
- [x] Configure Vite and Vue integration.
- [x] Initialize Git repository.
- [x] Create GitHub repository.
- [x] Establish Laravel and Vue SPA architecture.
- [x] Configure Vue Router and frontend navigation.
- [x] Configure Pinia and centralized Axios API client.
- [x] Integrate Laravel Sanctum stateful API middleware.
- [x] Establish API versioning under `/api/v1`.
- [x] Prepare User, Admin, Authentication and Error page structures.
- [x] Organize page components and dedicated stylesheets.
- [x] Document project architecture and frontend structure.
- [ ] Complete authentication interfaces and workflows.
- [ ] Implement backend authorization and role management.
- [ ] Design and implement the shared frontend design system.
- [ ] Develop User Dashboard.
- [ ] Develop Admin Dashboard.
- [ ] Implement task and project management.
- [ ] Implement categories and priority management.
- [ ] Develop calendar and scheduling functionality.
- [ ] Implement notifications and productivity statistics.
- [ ] Develop profile and settings functionality.
- [ ] Testing, security review and optimization.

*The roadmap distinguishes completed architectural preparation from features that still require implementation.*

## Project Goals

Planora is also a learning-oriented software engineering project focused on:

- Object-Oriented Programming (OOP).
- MVC architecture.
- SOLID principles.
- RESTful API development.
- Database design and relationships.
- Authentication and authorization.
- Web application security.
- Clean code and software architecture.
- Frontend and backend integration.
- Scalable application development.
- Git and collaborative development workflows.

## Author

**Elmir Velizade**

- GitHub: [@ElmirVelizadeDev](https://github.com/ElmirVelizadeDev)

---

**Planora — Plan your day. Shape your future.**
