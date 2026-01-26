---
enable: true # Contrôle la visibilité de cette section sur toutes les pages où elle est utilisée
title: Tarifs qui **font sens**

plans:
  enable: true
  list:
    # Liste des plans disponibles. Assurez-vous de utiliser ces noms de manière cohérente dans les endroits où cela est applicable.
    - selected: true
      label: À la séance # Utilisez cette valeur exactement dans tous les endroits correspondants ci-dessous.

list:
  # Plan de base
  - enable: true
    featured: false
    badge:
      enable: false
      label: Le plus populaire
    name: 1 séance # Nom du plan de tarification.
    description: Pour une séance de découverte, une envie de se reconnecter à soi-même ou une envie de décompresser.

    price:
      # Détails des prix pour chaque type de plan.
      - type: À la séance # Type de plan (doit correspondre aux valeurs dans la section "plans" ci-dessus).
        prepend_value:
        value: 60
        append_value: €
        indication: séance
      - type: Abonnements # Type de plan (doit correspondre aux valeurs dans la section "plans" ci-dessus).
        prepend_value:
        value: 55
        append_value: €
        indication: mois

    features:
    usages:

    cta_btn:
      enable: true
      label: Ouverture en août
      url: #contact
      rel:
      target:

  # Plan Medium
  - enable: true
    featured: false
    badge:
      enable: false
      label: Le plus populaire
    name: 2 séances # Nom du plan de tarification.
    description: 2 séances par mois suffisent pour instaurer une régularité, éveiller les sens et commencer à ressentir les bienfaits du Snoezelen.

    price:
      # Détails des prix pour chaque type de plan.
      - type: À la séance # Type de plan (doit correspondre aux valeurs dans la section "plans" ci-dessus).
        prepend_value:
        value: 55
        append_value: €
        indication: séance
      - type: Abonnements # Type de plan (doit correspondre aux valeurs dans la section "plans" ci-dessus).
        prepend_value:
        value: 99
        append_value: €
        indication: mois

    features:
    usages:

    cta_btn:
      enable: true
      label: Ouverture en août
      url: #contact
      rel:
      target:

  # Plan Pro
  - enable: true
    featured: true
    badge:
      enable: true
      label: Le plus populaire
    name: 4 séances # Nom du plan de tarification.
    description: 4 séances par mois, c'est la recommandation pour ressentir les effets bénéfiques de la Snoezelen sur le long terme.

    price:
      # Détails des prix pour chaque type de plan.
      - type: À la séance # Type de plan (doit correspondre aux valeurs dans la section "plans" ci-dessus).
        prepend_value: €
        value: 50
        append_value:
        indication: séance
      - type: Abonnements # Type de plan (doit correspondre aux valeurs dans la section "plans" ci-dessus).
        prepend_value: €
        value: 180
        append_value:
        indication: mois

    features:
    usages:

    cta_btn:
      enable: true
      label: Ouverture en août
      url: #contact
      rel:
      target:

# Comparaison des tarifs
comparison:
  #- label: Fonctionnalités
    #list:
      #- value: Intégrations
      #  included:
      #    - true # Plan gratuit
      #    - true # Plan Démarrage
      #    - true # Plan Pro
---
