# Stabilité du chargement — 4 novembre 2026

Corrections locales sur dev, sans déploiement :
- Espace réservé au chargement initial : le pied de page reste sous le premier écran pendant l’arrivée des données. Les autres indicateurs de chargement conservent leur présentation.
- Hauteur minimale du bandeau d’actualisation et gouttière de défilement stable.
- Dimensions explicites sur les logos et portraits, ajustées visuellement par le CSS existant.
- Petits logos hors écran chargés à la demande par le navigateur ; grands logos immédiatement chargés.
- Logos Highlightly en mode CORS anonyme : aucune transmission de cookies au fournisseur. En-tête Access-Control-Allow-Origin: * vérifié sur les images, puis chargement réel validé dans Chrome. Les autres hébergeurs de portraits gardent leur fonctionnement.

## Mesures locales

Chrome headless, build de production, fenêtres 390 × 844 et 1440 × 1000, trois contextes neufs par format et version. Animation et invitation quotidiennes déjà vues pour observer le contenu. Même copie des données publiques pour les deux versions, session simulée à 100 ms, statut à 500 ms, calendrier et classement à 800 ms chacun. Aucun appel à une API football pendant les scénarios. Logos réels chargés depuis leur CDN.

CLS (maximum des fenêtres de session de déplacements sans interaction, médiane de trois essais) :

| Format | Avant | Après |
| --- | --- | --- |
| Mobile | 0,25994 | 0 |
| PC | 0,86233 | 0,000161 |

La source principale mesurée était le déplacement du pied de page après le remplacement du chargement par les résultats. Le bandeau et l’apparition de la barre de défilement contribuaient également.

43 occurrences d’images au départ ; 25 chargées immédiatement après chargement différé, dans ces fenêtres. Les autres restent volontairement différées, ce ne sont pas des erreurs.

Test réseau avec un cookie tiers factice did préinstallé : 36 requêtes de logos transmettaient le cookie avant correction ; aucune après correction. Les images continuaient à se charger.

Vérifications supplémentaires : thèmes clair/sombre, largeurs 320/390/768/1440, accès sans compte, absence de débordement horizontal et d’exception JavaScript. Échec de tous les logos simulé : écussons de remplacement visibles. 38 tests JavaScript et compilation réussis.

## Limites

Ces mesures ciblées ne sont pas une nouvelle exécution Lighthouse et ne prédisent pas sa note. Elles n’utilisent pas la simulation réseau/CPU Moto G Power du rapport utilisateur. Refaire Lighthouse plusieurs fois dans les mêmes conditions après publication autorisée, en distinguant première visite et retour sans animation.

Le cache des images distantes reste géré par leurs fournisseurs (quatre heures observées pour Highlightly) ; aucun relais ni recopie des logos ajouté. Conditions consultées : https://highlightly.net/terms/.

Le contrôle des en-têtes publics confirme une CSP existante, avec unsafe-inline, sans HSTS ni COOP observés. Rien n’a été changé dans ces protections : leur durcissement demande une recette spécifique de l’authentification et des scripts. Trusted Types n’est pas activé. Les recommandations Lighthouse ne constituent pas, seules, une preuve de faille.
