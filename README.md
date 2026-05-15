# 🕊️ Volière API - Backend

API RESTful pour la gestion complète d'une volière de pigeons.

## Description

Application backend développée avec Laravel pour gérer une volière de pigeons. Elle permet de suivre les pigeons, former des couples, enregistrer les reproductions, gérer les cages et suivre les sorties (ventes, décès, pertes).

## Fonctionnalités

### Gestion des Pigeons
- CRUD complet avec validation
- Filtrage par statut (actif, sorti, décédé)
- Historique des mouvements
- Arbre généalogique (père, mère, descendants)
- Validation : statut ne peut pas revenir à "actif" si sortie enregistrée

### Gestion des Couples
- Formation de couples (mâle + femelle actifs)
- Modification limitée selon présence de descendants
- Rupture de couples
- Historique des couples
- Validation : pigeons déjà en couple ne peuvent pas être réutilisés

### Gestion des Reproductions
- Enregistrement des pontes
- Suivi des éclosions (minimum 17 jours après ponte)
- Création automatique des pigeonneaux
- Limitation biologique : maximum 4 jeunes par reproduction
- Validation : couple doit être actif

### Gestion des Cages
- CRUD des cages (numéro, nom, superficie)
- Affectation de pigeons ou couples
- Libération automatique
- Historique des affectations
- Validation : cage occupée ne peut pas être supprimée

### Gestion des Sorties
- Types : vente, décès, perte
- Libération automatique de la cage
- Rupture automatique du couple
- Validation : un pigeon ne peut avoir qu'une seule sortie

### Dashboard
- Statistiques globales (pigeons actifs, couples, reproductions)
- Reproductions récentes
- Vue d'ensemble de la volière

## Technologies

- **Framework** : Laravel 11
- **Base de données** : MySQL 
- **Authentification** : Laravel Sanctum (API tokens)
- **PHP** : 8.2+
- **Architecture** : RESTful API

## Prérequis

- PHP >= 8.2
- Composer
- MySQL >= 8.0 ou SQLite
- Extensions PHP : PDO, Mbstring, OpenSSL, JSON, Tokenizer

## Installation

### 1. Cloner le projet

```bash
git clone https://github.com/miss-ndeya/voliere-api.git
cd voliere-api
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configuration

```bash
# Copier le fichier d'environnement
cp .env.example .env

# Générer la clé d'application
php artisan key:generate
```

### 4. Configurer la base de données

Éditer `.env` :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=voliere
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Créer la base de données

```bash
mysql -u root -p
CREATE DATABASE voliere CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### 6. Exécuter les migrations

```bash
php artisan migrate
```

### 7. Créer un utilisateur

```bash
php artisan tinker
```

```php
\App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@voliere.com',
    'password' => bcrypt('password')
]);
```

### 8. Lancer le serveur

```bash
php artisan serve
```

L'API sera accessible sur `http://localhost:8000`

## Authentification

L'API utilise Laravel Sanctum pour l'authentification par token.

### Login

```http
POST /api/login
Content-Type: application/json

{
  "email": "admin@voliere.com",
  "password": "password"
}
```

**Réponse :**
```json
{
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@voliere.com"
  },
  "token": "1|abc123..."
}
```

### Utiliser le token

```http
GET /api/pigeons
Authorization: Bearer 1|abc123...
```

## Endpoints API

### Authentification
- `POST /api/login` - Connexion
- `POST /api/register` - Inscription
- `POST /api/logout` - Déconnexion

### Dashboard
- `GET /api/dashboard` - Statistiques globales

### Pigeons
- `GET /api/pigeons` - Liste des pigeons
- `POST /api/pigeons` - Créer un pigeon
- `GET /api/pigeons/{id}` - Détails d'un pigeon
- `PUT /api/pigeons/{id}` - Modifier un pigeon
- `DELETE /api/pigeons/{id}` - Supprimer un pigeon
- `GET /api/pigeons/{id}/history` - Historique
- `GET /api/pigeons-tous` - Tous les pigeons (pour généalogie)
- `GET /api/pigeons-disponibles` - Pigeons disponibles pour couples

### Couples
- `GET /api/couples` - Liste des couples
- `POST /api/couples` - Créer un couple
- `PUT /api/couples/{id}` - Modifier un couple
- `DELETE /api/couples/{id}` - Supprimer un couple
- `POST /api/couples/{id}/rompre` - Rompre un couple
- `GET /api/couples/{id}/history` - Historique
- `GET /api/couples-actifs` - Couples actifs (pour reproductions)

### Reproductions
- `GET /api/reproductions` - Liste des reproductions
- `POST /api/reproductions` - Créer une reproduction
- `PUT /api/reproductions/{id}` - Modifier une reproduction
- `DELETE /api/reproductions/{id}` - Supprimer une reproduction
- `POST /api/reproductions/{id}/pigeonneaux` - Créer les pigeonneaux

### Cages
- `GET /api/cages` - Liste des cages
- `POST /api/cages` - Créer une cage
- `PUT /api/cages/{id}` - Modifier une cage
- `DELETE /api/cages/{id}` - Supprimer une cage
- `POST /api/cages/{id}/affecter` - Affecter pigeon/couple
- `POST /api/cages/{id}/liberer` - Libérer une cage
- `GET /api/cages/{id}/history` - Historique
- `GET /api/cages-visualisation` - Vue pour visualisation
- `GET /api/cages-pigeons-disponibles` - Pigeons sans cage
- `GET /api/cages-couples-disponibles` - Couples sans cage

### Sorties
- `GET /api/sorties` - Liste des sorties
- `POST /api/sorties` - Créer une sortie
- `PUT /api/sorties/{id}` - Modifier une sortie
- `DELETE /api/sorties/{id}` - Supprimer une sortie

## Modèle de données

### Relations principales

```
User (1) ──── (N) Pigeon
Pigeon (N) ──── (1) Pigeon (père)
Pigeon (N) ──── (1) Pigeon (mère)
Couple (1) ──── (1) Pigeon (mâle)
Couple (1) ──── (1) Pigeon (femelle)
Reproduction (N) ──── (1) Couple
Cage (1) ──── (1) Pigeon ou Couple
Sortie (1) ──── (1) Pigeon
CageHistory (N) ──── (1) Cage
```

## Commandes utiles

```bash
# Réinitialiser la base de données
php artisan migrate:fresh

# Vider le cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Voir les routes
php artisan route:list

# Optimiser pour production
php artisan optimize
```

## Déploiement

### Variables d'environnement (production)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-api.com

DB_CONNECTION=mysql
DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SANCTUM_STATEFUL_DOMAINS=votre-frontend.com
SESSION_DOMAIN=votre-api.com
```

### Déploiement sur Railway

1. Créer un compte sur [Railway](https://railway.app)
2. Créer un nouveau projet
3. Ajouter une base de données MySQL
4. Connecter le repository GitHub
5. Configurer les variables d'environnement
6. Déployer
7. Exécuter : `php artisan migrate --force`

## Structure du projet

```
voliere-api/
├── app/
│   ├── Http/Controllers/    # Contrôleurs API
│   └── Models/              # Modèles Eloquent
├── database/
│   └── migrations/          # Migrations de base de données
├── routes/
│   └── api.php             # Routes API
└── .env.example            # Configuration exemple
```

## Tests

```bash
php artisan test
```

## Licence

Ce projet est développé dans un cadre éducatif.

## Auteur

Développé dans le cadre d'un projet de gestion de volière pour DTS.
