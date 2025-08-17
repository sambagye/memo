<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sujet;
use App\Models\Encadreur;

class SujetSeeder extends Seeder
{
    public function run(): void
    {
        $sujets = [
            [
                'titre' => 'Développement d\'un chatbot intelligent pour l\'assistance étudiante',
                'description' => 'Conception et développement d\'un système de chatbot utilisant l\'IA pour répondre aux questions des étudiants concernant les procédures administratives, les cours et l\'orientation.',
                'objectifs' => 'Créer une interface conversationnelle intelligente, intégrer des algorithmes de NLP, développer une base de connaissances évolutive.',
                'prerequis' => 'Python, Machine Learning, bases en NLP',
                'domaine' => 'Intelligence Artificielle',
                'niveau' => 'M2',
                'nombre_places_disponibles' => 2,
                'encadreur_email' => 'fatou.ndiaye@isi.sn',
                'statut' => 'valide',
            ],
            [
                'titre' => 'Plateforme e-commerce avec microservices',
                'description' => 'Développement d\'une plateforme e-commerce moderne basée sur une architecture microservices avec React et Node.js.',
                'objectifs' => 'Maîtriser l\'architecture microservices, implémenter des API RESTful, gérer la communication inter-services.',
                'prerequis' => 'JavaScript, React, Node.js, Docker',
                'domaine' => 'Développement Web',
                'niveau' => 'M2',
                'nombre_places_disponibles' => 1,
                'encadreur_email' => 'moussa.fall@isi.sn',
                'statut' => 'valide',
            ],
            [
                'titre' => 'Système de détection d\'intrusion basé sur l\'apprentissage automatique',
                'description' => 'Développement d\'un IDS utilisant des techniques d\'apprentissage automatique pour détecter les comportements anormaux sur un réseau.',
                'objectifs' => 'Analyser le trafic réseau, implémenter des algorithmes ML pour la détection d\'anomalies, évaluer les performances.',
                'prerequis' => 'Sécurité réseau, Python, Machine Learning',
                'domaine' => 'Sécurité Informatique',
                'niveau' => 'M2',
                'nombre_places_disponibles' => 1,
                'encadreur_email' => 'aminata.sarr@isi.sn',
                'statut' => 'valide',
            ],
            [
                'titre' => 'Application mobile de gestion de parc informatique',
                'description' => 'Conception d\'une application mobile permettant la gestion et le suivi des équipements informatiques d\'une entreprise.',
                'objectifs' => 'Développer une app mobile native, intégrer un système de QR codes, créer un dashboard administrateur.',
                'prerequis' => 'Développement mobile (Android/iOS), bases de données',
                'domaine' => 'Développement Mobile',
                'niveau' => 'M1',
                'nombre_places_disponibles' => 2,
                'encadreur_email' => 'moussa.fall@isi.sn',
                'statut' => 'valide',
            ],
            [
                'titre' => 'Optimisation de requêtes dans les bases de données distribuées',
                'description' => 'Étude et implémentation de techniques d\'optimisation pour améliorer les performances des requêtes dans un environnement de bases de données distribuées.',
                'objectifs' => 'Comprendre l\'optimisation de requêtes, implémenter des algorithmes d\'optimisation, évaluer les gains de performance.',
                'prerequis' => 'SQL avancé, systèmes distribués, algorithmique',
                'domaine' => 'Bases de Données',
                'niveau' => 'M2',
                'nombre_places_disponibles' => 1,
                'encadreur_email' => 'aissatou.wade@isi.sn',
                'statut' => 'valide',
            ],
            [
                'titre' => 'Système de monitoring réseau en temps réel',
                'description' => 'Développement d\'un outil de surveillance réseau permettant la détection et l\'alerte en temps réel des incidents.',
                'objectifs' => 'Créer un système de monitoring, implémenter des alertes automatiques, développer un dashboard de visualisation.',
                'prerequis' => 'Administration réseau, programmation système, Linux',
                'domaine' => 'Systèmes et Réseaux',
                'niveau' => 'M1',
                'nombre_places_disponibles' => 2,
                'encadreur_email' => 'ousmane.ba@isi.sn',
                'statut' => 'valide',
            ],
        ];

        foreach ($sujets as $sujetData) {
            $encadreur = Encadreur::whereHas('user', function($query) use ($sujetData) {
                $query->where('email', $sujetData['encadreur_email']);
            })->first();

            if ($encadreur) {
                Sujet::create([
                    'titre' => $sujetData['titre'],
                    'description' => $sujetData['description'],
                    'objectifs' => $sujetData['objectifs'],
                    'prerequis' => $sujetData['prerequis'],
                    'domaine' => $sujetData['domaine'],
                    'niveau' => $sujetData['niveau'],
                    'nombre_places_disponibles' => $sujetData['nombre_places_disponibles'],
                    'encadreur_id' => $encadreur->id,
                    'statut' => $sujetData['statut'],
                    'date_validation' => now(),
                ]);
            }
        }
    }
}
