# DBU Gym Client

Frontend application for the DBU Gym membership system. This app is built with React and Vite, and provides separate member and admin experiences on top of a backend API.

## What It Includes

- Public landing page with hero, about, apparatus, pricing, and contact sections
- Authentication flows for login, registration, and forgot password
- Role-based protected routes for members and admins
- Member dashboard and profile management
- Membership renewal flow
- Admin dashboard for member management, approvals, profile, and system settings
- API helpers for auth, members, approvals, payments, backups, and profile uploads

## Tech Stack

- React 19
- Vite 8
- React Router 7
- Tailwind CSS 4
- ESLint 9

## Project Structure

```text
client/
|- public/                  Static assets
|- src/
|  |- assets/               Images and bundled media
|  |- auth/                 Auth provider, context, protected routes
|  |- components/           Shared UI sections and navigation
|  |- lib/
|  |  |- api.js             API client and request helpers
|  |  `- theme.js           Theme tokens/helpers
|  |- pages/
|  |  |- admin/             Admin pages
|  |  `- members/           Member pages
|  |- App.jsx               Route registration
|  |- App.css               App-level styles
|  |- index.css             Global styles
|  `- main.jsx              App bootstrap
|- index.html
`- package.json
```

## Getting Started

1. Install dependencies:

```bash
npm install
```

2. Configure the backend base URL:

```bash
VITE_API_BASE=http://localhost/gym-website/server/public
```

You can place this in a `.env` file in the `client` directory.

3. Start the development server:

```bash
npm run dev
```

4. Build for production:

```bash
npm run build
```

5. Preview the production build locally:

```bash
npm run preview
```

## Available Scripts

- `npm run dev` starts the Vite dev server
- `npm run build` creates a production build
- `npm run preview` serves the production build locally
- `npm run lint` runs ESLint

## Backend Integration

The client expects a backend that exposes routes under `VITE_API_BASE`, including endpoints for:

- `/api/auth/*`
- `/api/member/*`
- `/api/admin/*`
- `/api/payments/chapa/*`

The repository also includes a database schema file at [`schema_v2.sql`](C:/Users/Admin/Documents/AMD/gym-collab/schema_v2.sql).

## Notes

- Auth state is stored in `localStorage` using the app token helpers in `src/lib/api.js`.
- The UI supports light/dark theme persistence.
- Member and admin screens rely on authenticated API responses, so the backend should be running during local development.
