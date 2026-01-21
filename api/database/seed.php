#!/usr/bin/env php
<?php
/**
 * Script de seeding de la base de données
 *
 * Usage:
 *   php database/seed.php          # Ajoute des données sans supprimer
 *   php database/seed.php --clean  # Supprime les données existantes puis recrée
 *
 * Crée:
 *   - 8 utilisateurs (2 associations, 6 particuliers)
 *   - 15 personnes (bénéficiaires)
 *   - Réservations passées (3 derniers mois) avec leurs séances
 *   - Réservations futures (35 prochains jours)
 */

require_once __DIR__ . '/Factory.php';

use App\Database\Factory;

$clean = in_array('--clean', $argv);

$factory = new Factory();
$factory->run($clean);
