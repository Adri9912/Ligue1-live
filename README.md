# Ligue 1 Live ⚽

Site responsive en français, construit avec Vue 3, Vite et Tailwind CSS. Un serveur Node.js protège la clé API et sert aussi le site compilé en production. Node.js **22.12 minimum**, Node 24 recommandé.

## Démarrage rapide

Dans un terminal ouvert dans ce dossier :

```bash
npm install
npm run dev
```

Ouvrir **http://127.0.0.1:5173**. Garder le terminal ouvert. `Ctrl+C` arrête les deux serveurs. Si les dépendances sont déjà installées, `npm run dev` suffit. Les ports 5173 et 3001 doivent être libres.

## Ce qui fonctionne

- Matchs classés par journée et par date ; filtres tous, en cours, à venir et terminés.
- Navigation entre les journées, filtre sur l’équipe favorite.
- Classement complet, fiches équipes, résultats et prochaines rencontres.
- Sur l’accueil : top 3 buteurs et top 3 passeurs, avec club, total et lien vers chaque classement complet ; chargement et erreurs indépendants des matchs.
- Sous « Classement » : onglets Équipes, Buteurs et Passeurs (noms et chiffres fictifs en démonstration).
- Classement provisoire calculé avec les scores en cours, retour au classement publié, variations de position et points rouges lumineux.
- Barres de couleurs pâles pour les zones européennes, le barrage et la relégation, avec légende explicite.
- Alertes du club favori réglables : début, score, résultat final ; aperçu test dans le site et notifications système sur autorisation.
- Page de match avec événements et statistiques disponibles.
- Choix, recherche, changement et retrait de l’équipe favorite.
- Thèmes clair et sombre et préférences enregistrées dans ce navigateur.
- Navigation mobile en bas d’écran, navigation ordinateur en haut.
- Chargement, états vides, erreurs, cache périmé et quotas gérés explicitement.
- Réglages d’actualisation : Automatique pendant les matchs, Manuel et Pause ; compteur du budget local et quota fournisseur observé lorsqu’il est transmis.

## Démonstration et vraies données

**Sans clé API, toutes les données sportives sont fictives.** La bannière le rappelle sur chaque page : scores, classement, statistiques, événements et joueurs sont inventés. Les blasons de démonstration sont des créations typographiques, pas des logos officiels. Les matchs en cours sont un scénario figé et ne simulent pas une source réelle. Les dates sont positionnées autour de la date de lancement. Les clubs de démonstration ne garantissent pas la composition de la saison réelle. Le classement de démo est calculé à partir de ses propres résultats fictifs.

### Activer Highlightly (fournisseur actuel)

1. Dans `.env`, choisir `FOOTBALL_PROVIDER=highlightly` et renseigner `HIGHLIGHTLY_API_KEY` avec une clé **Football API directe Highlightly**. Ne pas utiliser la clé API-Sports ou une clé RapidAPI.
2. Laisser `FOOTBALL_SEASON` vide pour la saison actuelle. Redémarrer `npm run dev` après tout changement de clé, fournisseur ou saison.
3. La clé reste exclusivement côté serveur. `.env` est ignoré par Git. Conserver `FOOTBALL_API_KEY` ne provoque aucun appel API-Sports lorsque Highlightly est sélectionné.

Le compte Basic gratuit a donné accès à la Ligue 1 **2026–2027**, au calendrier paginé, aux 18 clubs du classement, aux événements et aux statistiques d’un match terminé. Le calendrier affiche tous les matchs actuellement transmis par le fournisseur ; les journées futures non publiées ne sont pas inventées. Les formes récentes ne sont pas fournies dans le classement Highlightly et restent vides.

**Buteurs et passeurs :** la documentation Highlightly ne propose pas de classement global de compétition. Les pages correspondantes indiquent cette indisponibilité sans appel externe, sans réutiliser les données API-Football d’une autre saison. Reconstituer un classement demanderait des appels par match ou joueur et une validation de couverture ; ce n’est pas activé avec le quota gratuit.

Le cache et le budget Highlightly sont séparés dans `.cache/highlightly/football.json`. Chaque page de calendrier compte comme un appel (100 matchs par page, pagination complète obligatoire). Le rythme Auto, Manuel, Pause, le plafond local de 90 appels par jour et l’espacement des requêtes restent appliqués. Le quota du tableau de bord inclut aussi les appels de diagnostic effectués hors de l’application. Les favoris utilisent les identifiants du fournisseur : choisir à nouveau son club après une bascule.

Pour revenir à API-Football, choisir `FOOTBALL_PROVIDER=api-football`, renseigner une saison autorisée dans `FOOTBALL_SEASON`, puis redémarrer. Aucune bascule silencieuse entre fournisseurs.

### Activer API-Football

1. Choisir `FOOTBALL_PROVIDER=api-football`. Créer un compte gratuit sur https://dashboard.api-football.com/ et récupérer la clé **API-Sports directe** (ce connecteur n’utilise pas RapidAPI).
2. Copier `.env.example` sous le nom `.env`, dans ce dossier.
3. Ajouter la clé après `FOOTBALL_API_KEY=`. Ne pas la mettre dans le code Vue, ni dans une variable préfixée `VITE_`, ni dans un message public.
4. Laisser `FOOTBALL_SEASON` vide pour la saison en cours, ou renseigner son année de début (exemple : `2026` pour 2026–2027), uniquement si le forfait y donne accès.
5. Arrêter puis relancer `npm run dev`.
6. Vérifier la disparition de la bannière démo et les données retournées. En cas d’erreur, consulter le message affiché, les saisons autorisées et le quota du tableau de bord API.

**La connexion API-Football a été testée sur la saison 2024–2025. Le compte gratuit refuse la saison 2026–2027.** Le serveur vérifie les métadonnées de saison puis demande équipes, classement et matchs. Les possibilités d’une offre gratuite doivent être confirmées avec ce compte, surtout pour la saison actuelle et les statistiques. Une clé invalide ou une saison refusée produit une erreur ; l’application ne remplace jamais discrètement les vraies données par des données fictives.

### Limites de l’offre gratuite

Au moment de la préparation : API-Football annonce **100 requêtes par jour** et des saisons limitées. Voir https://www.api-football.com/pricing et https://www.api-football.com/documentation-v3.

- Budget local de 90 appels par jour UTC par défaut, configurable avec `FOOTBALL_DAILY_LIMIT` (1–100). Le quota réel peut aussi être consommé par d’autres outils utilisant la même clé.
- Appels espacés d’au moins 6,5 secondes, sans rafales de tentatives automatiques.
- Fréquences en mode Auto : calendrier, horaires et équipes une fois par jour de Paris ; scores toutes les 10 minutes pendant la plage d’un match ; classement quotidien et après un nouveau résultat, au plus une fois par heure ; buteurs et passeurs une fois par jour ; événements et statistiques d’un match à son ouverture, avec cache de 15 minutes. Les classements buteurs/passeurs sont demandés sur l’accueil (top 3) ou à l’ouverture de leur onglet, via `players/topscorers` et `players/topassists`.
- En mode Auto, les pages ouvertes vérifient les matchs à la prochaine échéance prévue, au plus toutes les 10 minutes, si elles sont visibles ou si les alertes du club favori sont actives ; aucune requête permanente lorsque le site est fermé. Le navigateur peut ralentir les vérifications en arrière-plan. Le bouton d’actualisation respecte aussi le cache et le mode choisi.
- Un premier chargement réel peut prendre quelques dizaines de secondes, car les appels sont espacés pour économiser le quota par minute.
- Les données en cache peuvent être affichées si le fournisseur échoue, avec avertissement et heure de mise à jour.
- Les statistiques manquantes restent indisponibles : une valeur absente n’est pas affichée comme zéro.
- Ce réglage privilégie un test gratuit ; il ne fournit pas des scores ou des notifications à la seconde.

Le cache et le compteur sont conservés dans `.cache/football.json`, sans clé. Ne pas supprimer ce dossier pour contourner le quota. Le compteur local ne peut pas connaître la consommation réalisée avec la même clé dans d’autres applications.

## Choisir quand appeler l’API

La barre « Auto / Manuel / Pause » et le bouton **Réglages** apparaissent sous la bannière. La préférence est conservée côté serveur dans `.cache/control.json` : elle est commune aux onglets, puis relue après un redémarrage.

- **Automatique pendant les matchs** : premier chargement, puis calendrier quotidien. À partir des horaires reçus, le site surveille la plage d’un match (au maximum quatre heures après le coup d’envoi), avec un intervalle minimal de 10 minutes pour les scores. Les résultats déclenchent ensuite la mise à jour du classement dans sa limite d’une heure. Les autres ressources sont chargées seulement lorsqu’elles sont demandées par une page et selon leur cache.
- **Manuel** : les ouvertures de pages affichent le cache, sans appeler le fournisseur. « Actualiser maintenant », les boutons d’actualisation et « Réessayer » sont des actions explicites. Les classements joueurs restent limités à un chargement par jour ; les scores manuels respectent un minimum de 10 minutes. Sans données en cache, une indication invite à lancer une actualisation.
- **Pause** : aucun nouvel appel au fournisseur, y compris pour une rubrique jamais ouverte. Les demandes encore en file sont interrompues ; un appel déjà envoyé peut se terminer. Aucune clé ni donnée fictive ne remplace silencieusement un cache absent.

Un contrôle local de `/api/status` a lieu une fois par minute pour relire les réglages et l’échéance : **ce n’est pas un appel au fournisseur de football**. Site fermé, aucun minuteur ne surveille les matchs. Les appels automatiques peuvent être retardés par les restrictions d’arrière-plan du navigateur.

Le compteur affiche les tentatives réellement envoyées par cette instance du serveur (les erreurs consomment aussi le budget), avec remise à zéro locale à minuit UTC, présentée en heure de Paris. Il ne peut pas comptabiliser les autres applications utilisant la même clé. Si le fournisseur renvoie son en-tête de quota restant, la dernière valeur observée et son heure sont indiquées séparément. Le contrôle de budget est appliqué côté serveur ; cliquer plusieurs fois ne contourne ni le quota ni le cache.

En démonstration, le compteur reste à zéro et les réglages sont testables sans clé. Les décisions de planification, les changements d’heure de Paris, le mode Manuel, la pause des appels en attente, le budget et la persistance des réglages sont couverts par les tests.

Le point de modification `/api/control` refuse les origines étrangères et exige du JSON. Le serveur actuel est local. Avant une mise en ligne partagée, réserver ce réglage global à un administrateur ou le protéger au niveau de l’hébergement : cette V1 n’a pas de comptes utilisateurs.

## Vérification

```bash
npm test
npm run build
```

Tests Node natifs : routes API, cohérence des liens de démonstration, statistiques manquantes, protection des routes, mutualisation des appels, cache après redémarrage, quota, erreurs fournisseur, projection sans double comptage, zones du classement, classements joueurs et règles d’alertes du favori, planification et contrôle des appels. Le workflow GitHub lance les tests et la compilation à chaque push et pull request.

Vérifications manuelles conseillées : thème et favori après rechargement ; navigation mobile ; filtres et journées ; match à venir et match en cours ; pages inexistantes ; clé invalide et saison refusée avec un compte de test. Aucun appel fournisseur payant n’est inclus dans les tests automatisés.

## Préparer la mise en ligne

```bash
npm ci
npm run build
npm start
```

Le serveur sert `dist/` et `/api` sur **http://127.0.0.1:3001** par défaut. Les liens directs vers les pages Vue sont pris en charge. Choisir un hébergement exécutant un processus Node, avec les variables secrètes définies côté serveur, `HOST=0.0.0.0`, le `PORT` fourni par l’hébergeur et HTTPS devant l’application.

Une **seule instance Node** est prévue. Pour conserver compteur et cache après un redéploiement, utiliser un disque persistant. Plusieurs instances nécessiteraient un compteur/cache partagé. Un hébergement exclusivement statique ne suffit pas à protéger la clé et exécuter cette API.

Aucune mise en ligne, création de dépôt distant ou souscription payante n’a été effectuée. Avant un lancement public, confirmer les droits d’utilisation des données et logos avec le fournisseur et dimensionner le quota selon l’audience.

## GitHub

Le `.gitignore` exclut `.env`, les dépendances, la compilation, les journaux et le cache. `.env.example` ne contient aucun secret. Depuis ce dossier uniquement, créer un dépôt privé ou public au choix, puis suivre les commandes proposées par GitHub. Ne jamais publier `.env`. En cas de clé divulguée, la régénérer chez le fournisseur.

## Organisation

```text
src/
  App.vue                Interface commune, thème et navigation
  state.js               Chargement des données et préférences
  components/            Cartes de match, blasons, tableau, choix du club
  views/                 Matchs, classement, équipe et détail de match
  style.css              Design et adaptations smartphone
server/
  index.mjs              API et serveur de production
  provider.mjs           API-Football, normalisation, cache et quota
  demo.mjs               Scénario fictif sans appel externe
scripts/dev.mjs          Démarrage des deux serveurs en développement
tests/app.test.mjs       Tests sans framework supplémentaire
```

## Classement provisoire et zones colorées

La vue « En direct » recalcule les points à partir des matchs terminés et des scores en cours. Elle ne rajoute pas aveuglément des points au classement du fournisseur, qui peut déjà inclure un direct. La simulation ne tient pas compte des sanctions administratives ou des départages avancés : le classement publié demeure la référence. Les données sont celles de la dernière récupération, pas un flux à la seconde. En démonstration, tous les résultats restent figés et fictifs.

Les couleurs sont des repères indicatifs pour une Ligue 1 à 18 clubs : 1–3 Ligue des champions ; 4 qualifications LDC ; 5 Ligue Europa ; 6 barrages de Ligue Conference ; 16 barrage de maintien ; 17–18 relégation. Les résultats de la Coupe de France et les règles UEFA peuvent modifier les places européennes. La légende contient le lien d’explication de la Ligue 1 ; les zones ne sont pas une annonce de qualification définitive.

## Notifications du club favori

Dans **Mon équipe**, activer les alertes, choisir les types, puis utiliser « Tester l’aperçu d’une alerte ». Le test est explicite et n’invente pas un événement réel.

- Les alertes sont désactivées par défaut. Aucune autorisation système n’est demandée au chargement.
- Le bouton séparé « Autoriser les notifications système » demande le consentement au navigateur. Il faut un navigateur compatible, HTTPS en production ou localhost en développement. Certains navigateurs intégrés et smartphones limitent cette possibilité. Voir https://developer.mozilla.org/en-US/docs/Web/API/Notifications_API/Using_the_Notifications_API.
- Sans autorisation, les alertes restent visibles dans le site. Les réglages et le club sont mémorisés sur ce navigateur.
- Garder au moins un onglet du site ouvert : **il n’y a pas de notifications push quand le site est fermé**. Le service worker sert uniquement à afficher les notifications et ouvrir le match associé, sans cache hors ligne ni collecte de données.
- La vérification suit les données toutes les 10 minutes, avec cache et quotas. Les minuteurs peuvent être ralentis ou suspendus par le navigateur en arrière-plan ou sur téléphone en veille. Une notification de but immédiate n’est pas garantie.
- Pas d’annonce d’anciens événements au premier chargement ou lors du changement de club ; les données périmées ne déclenchent pas d’alerte. Une baisse du score (VAR/correction) ne déclenche pas de faux but. Si la fin du match et un changement de score arrivent ensemble, une seule notification finale est émise.
- Les alertes automatiques dépendent de l’intégration des vraies données ; le scénario figé de démonstration permet uniquement de tester l’aperçu. Les Web Locks mutualisent le traitement entre onglets compatibles.
- La demande d’autorisation système et l’affichage natif restent à tester par l’utilisateur dans son propre navigateur ; l’aperçu dans le site, la mémorisation des options et les règles de détection ont été vérifiés.

## Prochaines versions

Notifications push même quand le site est fermé, source plus fraîche, site installable sur smartphone, éventuel compte pour synchroniser les préférences. Le projet reste consacré à la Ligue 1.


## Rétro et Clubs

Le menu **Rétro** propose 2022–2023, 2023–2024 et 2024–2025 avec classement final, résultats par journée et classements des buteurs/passeurs disponibles. Les accès restent soumis au forfait de la clé API-Football conservée dans `FOOTBALL_API_KEY`. Les données sont demandées à l’ouverture de chaque rubrique et mises en cache 30 jours. Elles ne remplacent pas la saison actuelle dans le navigateur. Les anciennes équipes et les matchs sont affichés sans liens vers les fiches de la saison actuelle.

Le menu **Clubs** liste les équipes du championnat actuel. Chaque fiche affiche l’effectif actuel transmis par `players/squads` (cache 24 h), les noms, postes et numéros, triés gardiens, défenseurs, milieux, attaquants. Cet endpoint donne l’effectif actuel, sans filtre de saison ni garantie d’exhaustivité des inscriptions en Ligue 1. Les noms peuvent être abrégés ; les numéros manquants restent absents. Une erreur d’effectif n’empêche pas d’afficher le stade, l’année de création et le palmarès disponibles.

Les fiches administratives viennent de `teams?country=France` (cache 30 jours). Les identifiants des deux fournisseurs sont rapprochés par nom exact normalisé et deux alias explicites, sans correspondance floue. Toute ambiguïté est signalée. Des corrections documentées concernent Monaco (fondation 1924), Toulouse (club actuel fondé en 1970) et Paris FC (stade Jean-Bouin). Les liens officiels sont fournis sur les fiches.

Le palmarès de première division depuis 1932 est un petit jeu de données éditorial, sourcé à la LFP et vérifié le 27 octobre 2026. Il exclut D2, coupes et championnats de guerre, et ne donne pas le titre 1992–1993 à Marseille. Les titres postérieurs à la date de vérification nécessiteront une mise à jour. Un club inconnu n’est jamais assimilé à un club sans titre.

Rétro et Clubs partagent **une seule instance du client API-Football** : cache `.cache/football.json`, compteur, file d’attente et budget de 90 appels. Ils respectent les modes Auto, Manuel et Pause. Le budget Highlightly de la saison actuelle reste distinct. Aucun appel API-Football n’est fait à chaque démarrage ; seules les rubriques ouvertes chargent leurs données. Les deux clés restent exclusivement dans `.env`.


## Photos, fiches joueurs et dernière composition

Les portraits d’effectif sont affichés depuis le CDN public API-Sports, sans clé dans leur URL, avec chargement différé et silhouette de remplacement. Cliquer un joueur ouvre une fenêtre accessible (dialogue natif, fermeture par Échap, retour du focus). La fiche `players/profiles?player=...` est contrôlée côté serveur : le joueur doit appartenir à l’effectif actuel du club demandé. Noms complets, naissance, nationalité, taille, poids et âge sont affichés lorsqu’ils existent. Cache 7 jours, même client et budget API-Football que Clubs/Rétro. Les profils n’incluent pas les statistiques de la saison actuelle.

Les compositions viennent de Highlightly et partagent son client, sa file et son budget. La recherche ne porte que sur les matchs terminés en Ligue 1 dans la saison choisie. Trois matchs maximum sont consultés par demande, du plus récent au plus ancien ; arrêt à la première composition complète. Un bouton permet de remonter par lots supplémentaires si aucune n’est trouvée. Seules les formations connues, avec les 11 joueurs uniques répartis dans des lignes cohérentes, sont affichées. Une erreur réseau/quota ne devient jamais une composition vide. Les compositions complètes restent en cache 30 jours, les réponses vides 6 heures. Les modes Manuel et Pause restent appliqués côté serveur.

Le terrain indique le match, sa date, le score et la formation. Le gardien est en bas. Les identifiants Highlightly ne sont jamais utilisés pour demander un profil API-Football : le rapprochement exige un nom normalisé compatible (prénom ou initiale + nom), le même numéro lorsqu’il est transmis et une correspondance unique. Sinon, le joueur conserve son nom et son numéro Highlightly, sans portrait ni lien de profil.

La composition habituelle n’est pas calculée. Le nombre de matchs consultés et de compositions exploitables est affiché, sans confondre la dernière composition disponible avec une analyse de toute la saison.


La page **Mon équipe** réutilise le terrain de la dernière composition disponible, avec portraits et fiches joueurs lorsque les correspondances sont sûres. Un changement de favori réinitialise les données de la fiche et le dialogue joueur ; les réponses tardives de l’ancien club sont ignorées. Les endpoints et caches sont les mêmes que dans Clubs.

Le favori est une préférence du navigateur (`localStorage`, clés `l1-favorite` et `l1-favorite-source`), distincte du cache des données sportives sur le serveur. Il persiste après actualisation et fermeture du navigateur, mais pas après effacement des données du site ni dans un autre navigateur/appareil. Les identifiants étant propres au fournisseur, une bascule de fournisseur peut demander une nouvelle sélection.


### Logo de la Ligue 1

Le filigrane et le rappel dans le pied de page utilisent `public/images/ligue1-logo.png`, conservé localement (aucun chargement externe). Source : [Ligue 1 2024 Logo — Wikimedia Commons](https://commons.wikimedia.org/wiki/File:Ligue_1_2024_Logo.png), auteur Ligue 1, fiche PD-textlogo. L’image originale est conservée ; le masque CSS adapte sa couleur et son opacité au thème. Identité présentée par la [LFP le 27 mars 2024](https://www.lfp.fr/article/un-nouveau-logo-pour-la-ligue-1-a-compter-de-2024-2025). Le site reste un projet indépendant.
