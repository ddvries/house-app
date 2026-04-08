# Interieurstijl Dossier (PHP + MariaDB)

Volledige webapp met:

- Veilige login
- Twee rollen: `admin` en `gebruiker`
- Huizen aanmaken en bewerken
- Ruimtes aanmaken en bewerken
- Materialen aanmaken en bewerken
- Winkellinks per materiaal
- Bijlagen (afbeeldingen en PDF) per materiaal
- PDF-export per huis
- Admin gebruikersbeheer (aanmaken, bewerken, verwijderen)

## Beveiliging

- PDO prepared statements
- CSRF-bescherming op alle POST-formulieren
- Session hardening: httponly, samesite=strict, id-regeneratie
- Wachtwoorden met Argon2id
- Autorisatie op eigenaarschap in alle queries
- Veilige uploadvalidatie op mime-type en grootte
- Bestandsopslag buiten directe URL-access, alleen via gecontroleerde download

## Installatie

1. Kopieer `.env.example` naar `.env` en vul databasegegevens in.
2. Installeer dependencies:

   composer install

3. Voer migraties uit:

   php scripts/migrate.php

4. Maak eerste gebruiker aan:

   php scripts/create-admin.php --email=admin@example.com --password='VervangDitMetEenSterkWachtwoord!'

5. Maak optioneel een gewone gebruiker aan:

   php scripts/create-user.php --email=user@example.com --password='VervangDitMetEenSterkWachtwoord!'

6. Start lokaal:

   php -S localhost:8000

7. Open in browser:

   http://localhost:8000

## Opmerking over PDF

PDF-export gebruikt Dompdf via Composer. Als Dompdf niet geinstalleerd is, toont de exportpagina HTML-output als fallback.

## Rollen en autorisatie

- `admin`: volledige toegang inclusief gebruikersbeheer via `/admin_users.php`.
- `gebruiker`: toegang tot huizen, ruimtes, materialen, bijlagen en export, maar geen gebruikersbeheer.

