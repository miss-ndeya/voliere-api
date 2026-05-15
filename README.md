# Baay Pitàq — API REST

API Laravel pour la gestion d’une volière de pigeons. Consommée par l’interface web et l’application mobile.

## URLs

- **Base API** : https://voliere-api-production.up.railway.app/api
- **Login** : https://voliere-api-production.up.railway.app/api/login
- **Web** : https://voliere-web.vercel.app/login

## Contexte et objectifs

Centraliser les données de l’élevage : pigeons, couples, reproductions, cages, sorties. Appliquer les règles métier côté serveur (statuts, affectations, unicité des couples, etc.).

## Fonctionnalités API

- Authentification Sanctum (login, logout, profil)
- CRUD pigeons, couples, reproductions, cages, sorties
- Dashboard et visualisation volière (`/cages-visualisation`)
- Affectation / libération de cages
- Rupture de couple, création de pigeonneaux
- Historiques pigeon et cage

## Règles métier (résumé)

- Pigeon actif : une cage max, un couple actif max
- Couple : un mâle + une femelle
- Cage : un pigeon OU un couple
- Sortie : met à jour le statut et libère la cage
- Pigeon avec descendants : pas de suppression définitive

## Stack

Laravel 11, MySQL, Sanctum, PHP 8.2+

## Prérequis

- PHP 8.2+
- Composer
- MySQL 8+

## Installation locale

```bash
git clone <url-du-repo-voliere-api>
cd voliere-api
composer install
cp .env.example .env
php artisan key:generate
```

Configurer `DB_*` dans `.env`, puis :

```bash
php artisan migrate
php artisan db:seed --class=UserSeeder
php artisan serve
```

API locale : http://localhost:8000

## Comptes de test

| E-mail | Mot de passe | Seeder |
|--------|----------------|--------|
| baaypitaq@voliere.com | 123456 | UserSeeder |
| demo@voliere.com | password | DemoSeeder |

## Authentification

```http
POST /api/login
Content-Type: application/json

{
  "email": "baaypitaq@voliere.com",
  "password": "123456"
}
```

Réponse : `token` à envoyer en `Authorization: Bearer {token}`.

## Déploiement sur Railway

1. Connecter le dépôt GitHub du backend.
2. Ajouter un service **MySQL** et lier les variables `DB_*`.
3. Variables : `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://voliere-api-production.up.railway.app`.
4. Après un push avec **nouvelles migrations** :

```bash
php artisan migrate --force
```

5. Ne pas exécuter `migrate:fresh` en production.

## Endpoints principaux

| Méthode | Route |
|---------|--------|
| POST | `/api/login`, `/api/logout` |
| GET | `/api/user`, `/api/dashboard` |
| apiResource | `/api/pigeons`, `/couples`, `/reproductions`, `/cages`, `/sorties` |
| GET | `/api/cages-visualisation` |
| POST | `/api/cages/{id}/affecter`, `/api/cages/{id}/liberer` |
| POST | `/api/couples/{id}/rompre` |
| POST | `/api/reproductions/{id}/pigeonneaux` |

## Dépôt frontend

Interface React séparée : voir le README du dépôt `voliere-web`.  
Variable frontend : `VITE_API_URL=https://voliere-api-production.up.railway.app/api`
