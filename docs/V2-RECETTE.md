# V2 — recette locale du 17 novembre 2026

La V2 conserve Vue, Laravel, les routes et les fonctions de V1. Son habillage bleu nuit, bleu et or s'applique maintenant à toutes les vues via le conteneur commun et les styles partagés. Aucun déploiement n'a été effectué.

## Versions

| Version | Dossier | Branche |
| --- | --- | --- |
| V1 conservée | `/home/adrien/Projets perso/Ligue1-Live` | `dev` |
| V2 | `/home/adrien/Projets perso/Ligue1-Live-V2` | `dev-v2` |

Au début de cette recette, `app/`, `routes/`, `config/`, les migrations et les tests étaient identiques dans les deux copies. Aucun fichier de V1 n'a été modifié pendant l'intervention : les empreintes des 194 fichiers suivis et fichiers d'environnement vérifiés sont inchangées. Les sections historiques de PROJECT_CONTEXT.md qui parlent de « V2 » concernent d'anciens lots fonctionnels, pas cette nouvelle branche graphique.

## Démarrer et tester

```sh
cd '/home/adrien/Projets perso/Ligue1-Live-V2'
npm run dev
```

Interface : http://127.0.0.1:5174/ ; Laravel : http://127.0.0.1:8001/. Le lanceur sait redémarrer l'instance MySQL privée déjà initialisée si elle est arrêtée. Ne pas démarrer deux lanceurs V2 simultanément. La configuration et les dépendances de cette machine sont déjà préparées ; une nouvelle copie nécessite `npm ci`, `composer install`, une APP_KEY propre et ses bases locales.

Comptes **fictifs**, réservés à cette base locale :

| Adresse | Mot de passe de test | Rôle |
| --- | --- | --- |
| `test-v2@example.test` | `Test V2 local 2026!` | Membre, adresse vérifiée |
| `admin-v2@example.test` | `Test V2 local 2026!` | Administrateur, adresse vérifiée |

Le compte `recette-v2@example.test`, avec le même mot de passe fictif, a été créé par le formulaire du navigateur, a suivi Angers puis a été vérifié via un lien signé local. Les deux comptes ci-dessus peuvent être recréés avec `php artisan db:seed --class=V2LocalSeeder` : ce seeder refuse les environnements autres que local, une autre base, une URL de base ou un transport différent de `array`. Il ne remplace pas le mot de passe d'un compte déjà présent.

## Isolation

- Instance MySQL propre à V2 : dossier privé `/home/adrien/tmp/ligue1-live-v2/mysql`, socket `/home/adrien/tmp/ligue1-live-v2/mysql.sock`, sans écoute TCP.
- Développement : base `ligue1_live_redesign`, utilisateur restreint à cette base.
- Tests : base `ligue1_live_test` **dans cette instance V2**, utilisateur distinct restreint à cette base. Le nom reste celui qu'imposent les garde-fous existants ; ce n'est pas la base de tests V1, qui utilise une autre instance/socket. L'utilisateur de tests ne peut pas voir la base de développement.
- Les migrations ont été exécutées uniquement sur la base de développement V2 ; les tests rafraîchissent leur base dédiée.
- Sessions en base, cookie V2 distinct, APP_KEY V2, cache fichier propre au dossier V2. `.env` et `.env.testing` sont privés et exclus de Git.
- Mail `array`, expéditeur fictif `test-v2@example.test` : aucun e-mail externe. Les messages sont simulés en mémoire et ne constituent pas une boîte de réception consultable. Les messages de confirmation de l'interface restent ceux du parcours réel.
- Rappels disponibles pour tester leurs préférences, mais données football en `pause` et aucun planificateur/worker automatique démarré.

Pour réinstaller ailleurs, créer les deux bases et leurs utilisateurs sur une instance locale indépendante, renseigner le socket et les identifiants privés dans les deux fichiers d'environnement, puis exécuter `php artisan migrate`. Vérifier la cible avant les tests : `APP_ENV=testing`, connexion MySQL, `DB_URL` vide, base `ligue1_live_test` et utilisateur limité à cette base. Ne jamais reprendre les identifiants ou le socket V1 ni une connexion de production.

## Données sportives et logos

63 réponses sportives du cache local V1 et 50 réponses de portraits publics encore valides ont été copiées vers le cache V2. Aucun compte, session, compteur de quota ou fichier privé de V1 n'a été transféré. Les clés fournisseur existantes restent uniquement dans le fichier privé V2, sans préfixe VITE_.

Lecture du cache vérifiée avec les requêtes HTTP non simulées interdites : 207 rencontres, 18 clubs, 18 adresses de logos. Les deux écussons de l'affiche et plusieurs portraits ont été effectivement chargés dans le navigateur. Les images distantes sont chargées depuis leurs hébergeurs habituels ; le mode pause concerne les appels des services football, pas les fichiers image. Les portraits absents gardent le repli en initiales existant.

Le cache date principalement des 3 et 7 novembre. Le site indique les dates et l'état ancien des données. Ce ne sont pas des scores en temps réel. Le compteur football V2 reste à zéro appel aujourd'hui. Le flux public Maxifoot continue son fonctionnement normal et son propre cache. Sans clé, le mode démonstration clairement signalé reste disponible et couvert par les tests.

## Modifications

- `src/App.vue` : applique le conteneur premium à toutes les routes, sans modifier leurs composants fonctionnels.
- `src/main.js` et `src/premium-pages.css` : chargent le thème partagé, les titres sportifs, les panneaux, formulaires, menus, tableaux, détails de match et fiches joueur. Adaptations mobile et thème clair ; réduction des animations respectée par CSS.
- `src/premium-home.css` : corrige l'affiche masquée sous 800 px. Les choix d'accueil validés restent en place : actualités en haut, classement puis Mon équipe, statistiques en largeur.
- `.env.example` : URL, base et noms de cookie/cache propres à V2 ; expéditeur fictif valide pour les essais `array`.
- `database/seeders/V2LocalSeeder.php` : deux comptes fictifs pour la recette locale, avec garde-fous.
- `tests/TestCase.php` : bloque les requêtes HTTP non simulées dans tous les tests Laravel.
- `tests/Feature/AccountsTest.php` : rend réellement un e-mail de vérification via `array`, vérifie sa présence et son expéditeur ; vérifie le refus du seeder en environnement de tests.

Le premier essai navigateur a trouvé un expéditeur vide qui provoquait une erreur après l'enregistrement du compte. La configuration locale et l'exemple ont été corrigés ; une nouvelle inscription complète a ensuite réussi. Aucun contrôleur, service sportif, route ou migration de production n'a été réécrit.

## Résultats

| Vérification | Résultat |
| --- | --- |
| `npm test` | 44 tests réussis |
| `php artisan test` | 129 tests réussis, 1387 assertions, base V2 dédiée |
| `npm run build` | Réussi |
| `bash tests/deploy.test.sh` | Réussi, déploiement/restaurations simulés dans des dossiers temporaires |
| `git diff --check` | Réussi |
| Navigateur, comptes | Inscription, déconnexion, connexion, renvoi de vérification, lien signé et mot de passe oublié via mail simulé : réussis |
| Navigateur, favoris | Club suivi et préférence de rappel enregistrés ; prochain match affiché |
| Navigateur, administration | Connexion administrateur, ouverture via menu, tableaux et budgets : réussis |
| Navigateur, sports | Accueil, classement, statistiques, actualités, clubs, équipe, match et fiche joueur : ouverts avec le cache |
| Responsive | 11 routes à 320, 390, 768 et 1024 px : aucun débordement de page ; bureau 1440 px vérifié visuellement |
| Thèmes | Nuit, plus classement/clubs/compte en thème clair mobile vérifiés |
| Menu/clavier | Menu mobile compilé, fermeture Échap et retour du focus au bouton : réussis |
| Build servi par Laravel | Accueil et classement ouverts sur 8001, styles compilés et manifeste présents, aucune erreur JavaScript remontée pendant cette vérification |

La récupération du mot de passe complète (jeton, expiration, usage unique), les permissions, quotas, sessions, suppression, rappels, file de tâches et protections sont couverts par les tests backend. Le mot de passe n'a pas été changé manuellement dans le navigateur. Les tests d'installation simulant les événements du navigateur passent.

## Limites et publication

- Pas d'essai d'installation sur téléphone physique. Le raccourci existant dépend de l'événement `beforeinstallprompt` et ne s'est pas affiché dans le navigateur de recette. La présence du manifeste et les tests simulés ne garantissent pas l'installation sur chaque téléphone.
- Pas d'envoi SMTP réel, de livraison d'un rappel réel, ni d'actualisation sportive en direct : ces essais sont volontairement simulés ou en pause. Certaines archives, fiches ou compositions sans cache peuvent rester indisponibles ; ne pas inventer leurs données.
- La préférence de réduction des animations est prise en compte dans le code GSAP existant et dans la feuille partagée ; aucun changement de préférence système n'a été imposé pendant la recette.
- Les métadonnées internes `.impeccable/design.json` sont anciennes ; leur régénération via `document` est facultative et n'a pas été réalisée pendant cette recette.
- `origin/dev-v2` contient déjà le premier lot visuel `fb461b8`. Ce nouveau lot reste local, sans commit ni push à cette étape. Le workflow de déploiement reste désactivé dans V2. Aucune fusion dans `main`, connexion à la base publique ou modification du serveur public.
- Avant une future mise en production : contrôler le diff avec `main`, préserver la configuration privée du serveur et vérifier explicitement le workflow de déploiement. Le succès de cette recette locale ne vaut pas validation du raccordement de production.

Demander l'accord de l'utilisateur avant tout push de ce lot sur `dev-v2`.
