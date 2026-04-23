# État de l'art - autoPMR

## 1. Introduction

Dans un projet de fin d'études, l'état de l'art a pour objectif de situer le travail réalisé par rapport aux solutions, approches et outils déjà existants dans le domaine étudié. Il ne s'agit pas uniquement de présenter des technologies connues, mais surtout d'analyser leurs apports, leurs limites, puis d'expliquer en quoi le projet développé répond à un besoin précis.

Dans le cas de `autoPMR`, le domaine étudié est celui du suivi des pull requests, de la supervision des workflows de revue de code, de la notification des équipes de développement et de l'automatisation des traitements liés aux événements GitHub. Le projet s'inscrit donc à l'intersection de plusieurs axes :

- les plateformes d'hébergement de code source
- les mécanismes de revue de code collaboratifs
- les systèmes de notification et de suivi des événements
- les outils d'orchestration et d'automatisation
- les approches émergentes d'assistance à la revue par intelligence artificielle

Le présent état de l'art est rédigé en cohérence avec le périmètre réel du code source inspecté. Il sert à positionner `autoPMR` comme une solution spécialisée de monitoring et de supervision des pull requests GitHub.

## 2. Les plateformes modernes de gestion de code source

### 2.1 Généralisation des forge logicielles

Le développement logiciel moderne repose largement sur des plateformes de gestion de code source telles que GitHub, GitLab ou Bitbucket. Ces plateformes permettent de centraliser les dépôts Git, de gérer les branches, de suivre les tickets, d'automatiser les pipelines CI/CD et surtout de structurer la collaboration via les pull requests ou merge requests.

Ces plateformes ont progressivement dépassé le simple rôle d'hébergement du code. Elles sont devenues de véritables environnements de collaboration qui centralisent plusieurs dimensions du cycle de développement :

- versionnement du code
- contrôle d'accès
- revue de code
- intégration continue
- gestion des commentaires et discussions
- suivi de l'historique des changements

Dans ce contexte, la pull request joue un rôle central car elle représente à la fois une unité de livraison, une demande de revue, un point de contrôle qualité et un espace de discussion technique.

### 2.2 Limites des plateformes généralistes pour le suivi avancé

Même si les plateformes comme GitHub offrent déjà des mécanismes de notification, de filtrage et de consultation des pull requests, elles restent d'abord conçues comme des plateformes généralistes. Leur objectif principal est de fournir un environnement complet de développement, et non une couche spécialisée de supervision focalisée sur le suivi fin des pull requests d'une équipe.

Dans les organisations où plusieurs dépôts sont actifs en parallèle, plusieurs difficultés apparaissent :

- dispersion des informations entre plusieurs dépôts
- difficulté à visualiser les pull requests nécessitant une action rapide
- manque de centralisation des PRs attribuées, bloquées ou en attente de revue
- surcharge informationnelle causée par les notifications natives
- faible personnalisation métier du suivi à l'échelle équipe

Ces limites justifient l'apparition d'outils complémentaires qui se branchent aux plateformes de code source afin d'offrir une vue plus ciblée et plus exploitable.

## 3. La revue de code comme pratique centrale

### 3.1 Importance de la revue de code

La revue de code est aujourd'hui considérée comme une pratique essentielle dans les processus de développement logiciel modernes. Elle améliore la qualité du code, favorise la diffusion des connaissances dans l'équipe, réduit les régressions et permet de vérifier la conformité aux conventions techniques et métier.

Dans les workflows basés sur GitHub, la pull request matérialise cette revue. Elle regroupe :

- le code modifié
- les fichiers impactés
- l'historique des commits
- les commentaires
- les reviewers demandés
- l'état de validation
- le résultat des vérifications CI

Cependant, plus le volume de pull requests augmente, plus le suivi manuel devient difficile. Une équipe peut rapidement perdre la visibilité sur :

- les PRs demandant une revue urgente
- les PRs approuvées mais non fusionnées
- les PRs bloquées par la CI
- les PRs devenues obsolètes
- les PRs sans propriétaire clair

### 3.2 Vers une supervision orientée action

Les pratiques récentes montrent un besoin croissant de passer d'une simple consultation des pull requests à une supervision orientée action. Cela signifie que les utilisateurs ne veulent pas seulement voir une liste de PRs ; ils veulent savoir immédiatement :

- lesquelles nécessitent leur intervention
- lesquelles sont en retard
- lesquelles ont déjà été validées
- lesquelles présentent un blocage technique
- lesquelles doivent être relancées

Cette évolution justifie le développement de tableaux de bord spécialisés et de systèmes de classification plus intelligents. Dans le projet `autoPMR`, cette logique apparaît clairement à travers le team dashboard, qui propose des vues comme :

- mes PRs rédigées
- PRs demandant ma revue
- PRs que j'ai approuvées
- PRs bloquées par la CI
- PRs sans propriétaire

## 4. Les systèmes de notification dans les workflows de développement

### 4.1 Rôle des notifications

Les notifications jouent un rôle critique dans les outils collaboratifs. Elles permettent d'alerter les utilisateurs lorsqu'un événement nécessite leur attention. Dans le contexte des pull requests, il peut s'agir par exemple :

- d'une nouvelle pull request ouverte
- d'une mise à jour de code après push
- d'un changement d'état de revue
- d'une conversion vers le mode draft
- d'une fermeture ou fusion

L'objectif d'un bon système de notification n'est pas d'envoyer le plus grand nombre d'alertes possible, mais d'envoyer les bonnes alertes aux bonnes personnes au bon moment.

### 4.2 Limites des notifications natives

Les plateformes comme GitHub proposent des notifications natives, mais celles-ci deviennent rapidement difficiles à exploiter dans les environnements riches en événements. Les principales limites sont :

- trop grand volume d'alertes
- difficulté à filtrer selon un périmètre métier précis
- manque de priorisation selon la responsabilité réelle de l'utilisateur
- difficulté à rattacher une notification à une action opérationnelle claire

Une surcharge de notifications produit l'effet inverse de l'effet recherché : l'utilisateur finit par ignorer les alertes.

### 4.3 Personnalisation comme réponse

Une tendance forte dans les systèmes de notification modernes consiste à rendre la notification configurable et contextualisée. Cette personnalisation peut porter sur :

- le type d'événement
- le dépôt concerné
- le rôle de l'utilisateur dans la pull request
- le niveau d'urgence
- le canal de diffusion

Le projet `autoPMR` s'inscrit dans cette logique à travers une gestion explicite des préférences utilisateur :

- activation ou désactivation globale des e-mails
- choix des événements déclencheurs
- restriction éventuelle à une liste spécifique de dépôts

Cela positionne la plateforme non comme un simple duplicateur des notifications GitHub, mais comme une couche de filtrage orientée utilisateur.

## 5. Les tableaux de bord de supervision pour équipes de développement

### 5.1 Passage du suivi individuel au suivi d'équipe

Les interfaces natives des plateformes de code sont souvent pensées pour une consultation dépôt par dépôt ou pull request par pull request. Or, dans une équipe, le besoin réel dépasse souvent cette logique individuelle. Les responsables techniques, reviewers réguliers et développeurs doivent disposer d'une vision consolidée de l'activité.

Les tableaux de bord de supervision répondent à ce besoin en fournissant :

- une vue consolidée de plusieurs dépôts
- des indicateurs synthétiques
- des regroupements par statut ou responsabilité
- des filtres rapides
- une lecture orientée décision

### 5.2 Indicateurs les plus pertinents

Dans la littérature professionnelle et dans les outils modernes de suivi d'équipes, certains indicateurs reviennent fréquemment :

- nombre de PRs ouvertes
- PRs nécessitant une revue
- PRs bloquées
- PRs obsolètes
- PRs approuvées
- temps de réponse ou temps de fusion
- activité récente par dépôt

Le projet `autoPMR` reprend une partie de cette logique avec deux niveaux de tableaux de bord :

- un tableau de bord général pour les indicateurs globaux liés aux dépôts et pull requests
- un tableau de bord d'équipe centré sur les snapshots locaux de pull requests et les vues de responsabilité

### 5.3 Visualisation exploitable et non seulement informative

L'efficacité d'un dashboard ne dépend pas seulement du volume d'informations affichées, mais de sa capacité à orienter rapidement l'action. Un bon tableau de bord doit permettre de répondre à des questions simples :

- Que dois-je traiter maintenant ?
- Quelle PR est bloquée ?
- Quelle PR attend ma revue ?
- Quel dépôt montre le plus d'activité ?

La valeur ajoutée d'un système comme `autoPMR` réside précisément dans cette dimension opérationnelle. Il ne se contente pas d'exposer des données GitHub, mais les restructure sous une forme exploitable par l'utilisateur.

## 6. L'automatisation des workflows et l'émergence des architectures événementielles

### 6.1 De l'application monolithique au système réactif

Les applications modernes intégrant des plateformes externes comme GitHub adoptent de plus en plus des architectures pilotées par événements. Lorsqu'un webhook est reçu, l'application ne traite plus forcément toute la logique en synchrone. Elle peut :

- vérifier l'authenticité du message
- contrôler l'idempotence
- publier une commande asynchrone
- déléguer les traitements lourds à un worker

Cette approche améliore la fiabilité, la résilience et la scalabilité.

Dans `autoPMR`, cette orientation est clairement visible à travers :

- l'usage de webhooks GitHub
- la vérification idempotente via `ProcessedWebhookDelivery`
- le dispatch de messages Messenger
- le traitement asynchrone de l'envoi des notifications
- le rafraîchissement asynchrone des snapshots de pull requests

### 6.2 Place des outils d'orchestration comme n8n

Les outils comme `n8n` répondent à un besoin croissant d'orchestration entre systèmes. Ils permettent d'intercepter des événements, de les filtrer, de les enrichir, puis de les redistribuer vers plusieurs cibles internes ou externes.

Dans le cas présent, `n8n` joue un rôle important :

- il reçoit les webhooks GitHub
- il transfère le payload original vers Symfony avec un mécanisme interne de confiance
- il filtre certains événements liés aux pull requests
- il prépare un point d'entrée pour des traitements complémentaires

Cette architecture traduit une tendance contemporaine : ne plus considérer l'application comme un bloc fermé, mais comme un système intégré dans un écosystème d'automatisation.

## 7. Les modèles de données de suivi local des événements GitHub

### 7.1 Dépendance aux APIs externes et besoin de projection locale

Une application qui se contente d'interroger directement l'API GitHub à chaque affichage devient rapidement limitée :

- dépendance forte à la disponibilité de l'API externe
- coûts en latence
- limites de taux
- difficulté à produire des agrégations adaptées aux besoins métier

Une tendance fréquente consiste donc à construire une projection locale des données utiles. Au lieu de tout recalculer à partir de l'API source, le système maintient un modèle local plus simple à interroger.

### 7.2 Le cas des snapshots

Le projet `autoPMR` adopte précisément cette approche via l'entité `PullRequestSnapshot`. Cette entité agit comme une projection persistée des pull requests ouvertes, enrichie avec :

- auteur
- statut
- statut de revue
- statut CI
- reviewers assignés
- reviews complétées
- labels
- état IA
- activité récente
- indicateur d'obsolescence

Cette stratégie est représentative des architectures modernes de projection locale. Elle permet :

- des requêtes rapides côté dashboard
- des agrégations SQL dédiées
- un filtrage orienté métier
- une indépendance partielle par rapport à l'API GitHub au moment de la consultation

### 7.3 Limite de la duplication

Ce type d'approche introduit néanmoins une question classique : faut-il stocker une projection globale partagée ou une projection spécifique à chaque utilisateur ? Le projet inspecté choisit actuellement un stockage de snapshots par utilisateur. Ce choix simplifie certaines vues personnalisées mais génère une duplication potentielle des données.

Cette observation est importante dans un état de l'art, car elle illustre un compromis fréquent entre personnalisation, simplicité d'implémentation et coût de stockage.

## 8. Assistance IA et nouvelles tendances autour de la revue de code

### 8.1 Montée des assistants IA dans les workflows de développement

Les outils de développement intègrent de plus en plus des fonctionnalités d'assistance par intelligence artificielle. Dans le domaine des pull requests, les usages émergents incluent :

- résumé automatique des changements
- détection des zones de risque
- aide à la revue de code
- catégorisation des anomalies potentielles
- priorisation des pull requests

Ces approches ne remplacent pas le reviewer humain, mais elles visent à réduire la charge cognitive et à accélérer la compréhension initiale d'une pull request.

### 8.2 Position du projet autoPMR

Le projet `autoPMR` ne contient pas encore un pipeline IA complet en production, mais plusieurs éléments de son code montrent une préparation explicite à cette évolution :

- présence des champs `aiReviewStatus`, `aiReviewSummary` et `aiIssueCount`
- endpoint interne de traitement de pull request
- intégration n8n permettant d'orienter certains événements vers une chaîne future de traitement

Ainsi, le projet s'aligne sur une tendance actuelle du domaine : enrichir les workflows de revue par une couche d'assistance intelligente. L'intérêt académique ici est important, car cela montre que l'architecture a été pensée pour accueillir une extension IA sans devoir être reconstruite entièrement.

## 9. Positionnement de autoPMR par rapport à l'état de l'art

### 9.1 Ce que le projet reprend des approches existantes

`autoPMR` s'inscrit dans plusieurs pratiques déjà reconnues :

- usage d'une forge Git moderne comme source d'autorité
- exploitation des webhooks pour déclencher les traitements
- usage d'une architecture asynchrone pour les opérations coûteuses
- création d'une projection locale pour les besoins de dashboarding
- personnalisation des notifications utilisateur
- séparation entre couche opérationnelle utilisateur et couche de supervision administrateur

### 9.2 Ce que le projet apporte comme spécialisation

Par rapport aux plateformes généralistes, `autoPMR` propose une spécialisation claire :

- focalisation sur le suivi des pull requests plutôt que sur l'ensemble du cycle DevOps
- vue consolidée orientée équipe
- vues de responsabilité directement exploitables
- filtrage métier des notifications
- couplage entre surveillance, dashboards et projection locale

Le projet ne cherche donc pas à remplacer GitHub, mais à le compléter par une couche de monitoring ciblée.

### 9.3 Position académique du projet

D'un point de vue académique, `autoPMR` constitue un projet pertinent car il matérialise plusieurs concepts actuels de l'ingénierie logicielle :

- intégration de systèmes hétérogènes
- traitement événementiel
- architecture en couches
- projection de données locales
- tableaux de bord orientés décision
- préparation à une extension IA

Le projet se positionne ainsi dans une zone intermédiaire intéressante entre application web métier, outil de productivité développeur et plateforme d'intégration logicielle.

## 10. Synthèse

L'état de l'art montre que le suivi des pull requests est devenu un enjeu important dans les environnements de développement modernes. Les plateformes comme GitHub fournissent les fondations du workflow collaboratif, mais elles laissent apparaître des limites dès lors qu'il s'agit d'assurer une supervision consolidée, personnalisée et orientée action à l'échelle d'une équipe.

Les tendances actuelles du domaine convergent vers plusieurs idées fortes :

- centraliser les signaux importants
- filtrer les notifications selon le contexte utilisateur
- utiliser des architectures événementielles et asynchrones
- projeter localement les données utiles pour le reporting opérationnel
- préparer l'intégration d'assistants intelligents pour réduire la charge de revue

Le projet `autoPMR` s'inscrit clairement dans cette dynamique. Il ne réinvente pas la forge logicielle, mais construit au-dessus d'elle une couche spécialisée de monitoring, de notification et de supervision. C'est précisément ce positionnement qui lui donne sa valeur dans le cadre d'un PFE : il répond à un besoin réel, s'appuie sur des pratiques modernes d'architecture logicielle et ouvre des perspectives crédibles d'évolution vers plus d'automatisation et d'intelligence métier.

