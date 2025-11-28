# Space Station 14 Render Viewer

Web application for viewing renders from the Space Station 14 game. Allows developers to upload and manage render collections through an admin panel, and users to view them with convenient filtering and panoramic viewing.

## About the Project

Space Station 14 Render Viewer is a system for organizing and viewing renders from the Space Station 14 game. The project provides:

-   **Filament Admin Panel** for managing render pages
-   **Dynamic pages** with automatic filter and category generation from JSON
-   **Panoramic image viewing** using panzoom (zoom, pan)
-   **Flexible filtering system** - filters are automatically generated from data
-   **Universal structure** - support for any additional fields in JSON

### Key Features

-   ✅ Create render pages through admin panel
-   ✅ Upload JSON files with render data
-   ✅ Automatic filter generation from item fields
-   ✅ Automatic grouping by categories (if `category` field is present)
-   ✅ Panoramic image viewing with zoom and pan
-   ✅ Dynamic display of additional fields
-   ✅ Responsive design

### Технологии

-   **Backend**: Laravel 12, PHP 8.2+
-   **Admin Panel**: Filament 4
-   **Frontend**: Tailwind CSS 4, JavaScript (ES6+)
-   **Image Viewer**: Panzoom
-   **Database**: MySQL/MariaDB

## Requirements

-   PHP 8.2 or higher
-   Composer
-   Node.js 18+ and npm
-   MySQL/MariaDB or other supported database
-   Web server (Apache/Nginx) or PHP built-in server

## Installation

### 1. Clone the repository

```bash
git clone <repository-url>
cd shipyard.local
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install Node.js dependencies

```bash
npm install
```

### 4. Configure environment

Copy the `.env.example` file to `.env`:

```bash
cp .env.example .env
```

Open `.env` and configure:

```env
APP_NAME="SS14 Render Viewer"
APP_URL=http://shipyard.local

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shipyard
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Generate application key

```bash
php artisan key:generate
```

### 6. Run migrations

```bash
php artisan migrate
```

### 7. Create storage symlink

```bash
php artisan storage:link
```

### 8. Build frontend assets

For production:

```bash
npm run build
```

For development:

```bash
npm run dev
```

### 9. Create admin panel user

```bash
php artisan make:filament-user
```

Follow the instructions to create an administrator.

## Running the Application

### Development Mode

To run in development mode with automatic frontend rebuild:

```bash
composer run dev
```

Or separately:

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Vite dev server
npm run dev
```

### Production Mode

1. Build frontend assets:

```bash
npm run build
```

2. Optimize the application:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3. Start web server (Apache/Nginx) or use built-in server:

```bash
php artisan serve
```

### Accessing the Admin Panel

After creating a user, the admin panel is available at:

```
http://your-domain/admin
```

## JSON File Structure

Each render page uses a JSON file with the following structure:

```json
{
    "renders_path": "renders/shuttles/",
    "items": [
        {
            "id": "Adder",
            "name": "LVHI Adder",
            "image": "adder.webp",
            "price": 18000,
            "description": "A small maneuverable shuttle...",
            "category": "Small",
            "class": ["Salvage"],
            "engines": ["Plasma"]
        }
    ]
}
```

**Important:**

-   `renders_path` - path to image directory relative to `storage/app/public/`
-   `items` - array of render objects
-   Required fields: `id`, `name`, `image`
-   Filters are automatically generated from all fields (except `id`, `name`, `image`, `description`)
-   If `category` field exists, it's used for grouping
-   Additional fields are handled universally

## Project Structure

```
shipyard.local/
├── app/
│   ├── Filament/Resources/      # Filament resources
│   ├── Http/Controllers/        # Controllers
│   ├── Models/                  # Eloquent models
│   ├── Services/                # Services (RenderPageService)
│   └── Providers/               # Service providers
├── database/
│   └── migrations/              # Database migrations
├── resources/
│   ├── css/                     # CSS styles (Tailwind)
│   ├── js/                      # JavaScript modules
│   └── views/                   # Blade templates
├── storage/
│   └── app/
│       ├── data/pages/          # JSON page files
│       └── public/renders/      # Render images
└── public/                      # Public directory
```

## Usage

### Creating a Render Page

1. Log in to the admin panel (`/admin`)
2. Navigate to "Render Pages"
3. Click "Create"
4. Fill in the form:
    - **Name** - page name
    - **Slug** - URL slug (auto-generated from name)
    - **JSON Path** - path to JSON file (e.g., `shuttles.json`)
    - **Description** - page description (optional)
    - **Sort Order** - sort order
    - **Is Active** - whether the page is active
5. Save

### Preparing a JSON File

1. Create a JSON file in `storage/app/data/pages/`
2. Specify `renders_path` - path to image directory
3. Add `items` array with render data
4. Upload images to `storage/app/public/renders/[your_path]/`

### Viewing Renders

-   Home page: `/` - list of all active pages
-   Render page: `/render/{slug}` - list of renders with filters
-   Render view: `/render/{slug}/{id}` - detailed view with panzoom

## Лицензия

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
