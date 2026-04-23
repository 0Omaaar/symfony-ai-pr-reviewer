# État de l'art - version courte

## Introduction

Aujourd'hui, les équipes de développement utilisent largement des plateformes comme GitHub pour gérer leur code source et collaborer à travers les pull requests. Ces plateformes offrent déjà des fonctionnalités de revue de code, de notifications et de suivi des modifications. Cependant, lorsque le nombre de dépôts et de pull requests augmente, il devient difficile pour les développeurs et les responsables techniques de garder une vision claire de ce qui nécessite une action.

## Limites des solutions existantes

Les solutions existantes, comme GitHub, sont très complètes, mais elles restent généralistes. Elles montrent les pull requests dépôt par dépôt, et les notifications deviennent rapidement nombreuses et difficiles à exploiter. Dans ce contexte, plusieurs problèmes apparaissent :

- manque de visibilité globale sur les pull requests importantes
- difficulté à savoir quelles PR demandent réellement une revue
- difficulté à identifier rapidement les PR bloquées par la CI ou devenues obsolètes
- surcharge de notifications peu ciblées

## Tendance actuelle

Pour répondre à ces limites, les outils modernes s'orientent vers :

- des tableaux de bord centralisés
- des notifications filtrées et personnalisées
- des architectures événementielles basées sur les webhooks
- des traitements asynchrones pour améliorer la performance
- des mécanismes d'automatisation et, de plus en plus, d'assistance par intelligence artificielle

## Positionnement de autoPMR

Le projet `autoPMR` s'inscrit dans cette logique. Il ne remplace pas GitHub, mais vient le compléter par une couche spécialisée de suivi des pull requests. La solution permet :

- de centraliser les pull requests issues de plusieurs dépôts
- de proposer un tableau de bord orienté équipe
- de filtrer les PR selon la responsabilité de l'utilisateur
- de gérer des notifications e-mail selon les préférences utilisateur
- d'automatiser le traitement des événements GitHub à travers des webhooks et un système asynchrone

## Apport du projet

La valeur ajoutée de `autoPMR` est de transformer des informations GitHub dispersées en informations directement exploitables pour l'action. Au lieu de simplement consulter des pull requests une par une, l'utilisateur peut savoir rapidement :

- quelles PR demandent son attention
- quelles PR sont bloquées
- quelles PR sont obsolètes
- quelles PR ont déjà été approuvées

## Conclusion

En résumé, l'état de l'art montre que les plateformes existantes fournissent les bases du travail collaboratif, mais qu'il existe un besoin réel de solutions spécialisées pour le suivi opérationnel des pull requests. `autoPMR` répond à ce besoin en proposant une couche de supervision, de notification et d'automatisation adaptée aux équipes de développement modernes.

