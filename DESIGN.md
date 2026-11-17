# Ligue 1 Live V2 — direction issue des maquettes utilisateur

Référence principale : première maquette fournie le 13 novembre 2026. La seconde guide la déclinaison mobile. Conserver Vue, Laravel, le routage et les fonctions sportives existantes.

## Identité

- Fond nuit #020c1b ; surfaces #071a2f et #0b2440 ; texte #f2f6ff ; secondaire #b0c8e4.
- Bleu électrique #008aff, cyan #79caff, accent or #f4c364. Contours lumineux statiques, sans animation de flou.
- Titres du bandeau : Barlow ExtraBold Italic, auto-hébergée, licence OFL dans public/fonts.
- Marque existante avec deux barres géométriques cyan/bleu sur l’accueil.

## Accueil

Bandeau illustré d’un ballon bleu et de projecteurs ; titre blanc/or et boutons bleus. Barre de matchs en direct, ou prochaines rencontres si aucun direct. Desktop large : calendrier, classement et synthèse statistique sur trois colonnes. Tablette : deux colonnes puis synthèse. Mobile : empilement et navigation basse conservée. Actualités réelles issues du composant RSS existant, sans inventer de titres, images de presse ni catégories. Favoris et classements de joueurs conservés.

## Données et mouvement

Les indicateurs viennent du classement reçu ; une donnée absente affiche un tiret. Aucun taux de variation ni nombre de cartons inventé. Le mode démonstration reste signalé. GSAP anime le bandeau après l’introduction existante et se nettoie au démontage. Mouvement réduit respecté. Les scores restent stables pendant la lecture.

## Assets

Image public/images/stadium-electric-v2.webp générée par ImageGen à partir de la référence utilisateur, sans interface incrustée. Prompt et provenance dans le fichier .source.json voisin. Styles dédiés : src/premium-home.css, limités à la page d’accueil. Aucun déploiement ni modification du projet original.

## Validation

Compilation et 44 tests JavaScript reussis. Filtres statut/club, etat vide et remise a zero verifies dans le navigateur. Revue visuelle : ship, contraste du sous-titre mobile renforce. Verifications sans base de donnees, deploiement ni modification de l’original.

## Personnalisation validee par l’utilisateur

Le bandeau utilise desormais stadium-tunnel-v2.webp : entree de stade, atmosphère photographique, titre « Le championnat, au plus pres ». L’affiche est un panneau de premier plan avec equipes nommees, ecussons, grand score et lien de suivi. Le direct est regroupe dans ce panneau ; la barre de scores separee est retiree. Les deux actualites sont replacees immediatement apres le bandeau, avant le calendrier. Animation de profondeur et de lumiere sur le decor uniquement ; les chiffres restent stables. Le rendu n’imite plus litteralement la maquette. Le prompt exact du decor est conserve dans le fichier .source.json voisin.

## Reajustement de disposition

Sous les actualites : calendrier a gauche, classement puis Mon equipe dans la colonne droite. Statistiques sur toute la largeur en dessous, quatre indicateurs en ligne sur ordinateur et deux par ligne sur telephone. Les blocs top buteurs et top passeurs sont retires de l’accueil ; leurs pages existantes restent disponibles.
