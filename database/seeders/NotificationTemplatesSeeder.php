<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LetterTemplate;

class NotificationTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'club_assignment_standard',
                'type' => 'club',
                'subject' => 'Assegnazione Arbitri - {{tournament_name}}',
                'body' => "Gentile {{club_name}},\n\nVi comunichiamo gli arbitri assegnati per il torneo {{tournament_name}}:\n\n{{#each referees}}\n- {{name}} ({{role}}) - {{email}}\n{{/each}}\n\nIl torneo si svolgerà {{tournament_dates}}.\n\nCordiali saluti,\nSezione Zonale Regole",
                'is_active' => true,
                'is_default' => true,
                'variables' => json_encode(['club_name', 'tournament_name', 'tournament_dates', 'referees'])
            ],
            [
                'name' => 'referee_assignment_formal',
                'type' => 'referee',
                'subject' => 'Convocazione Ufficiale - {{tournament_name}}',
                'body' => "Gentile {{referee_name}},\n\nÈ ufficialmente convocato come {{assignment_role}} per:\n\n**{{tournament_name}}**\nDate: {{tournament_dates}}\nCircolo: {{club_name}}\n\nCordiali saluti,\nSezione Zonale Regole",
                'is_active' => true,
                'is_default' => true,
                'variables' => json_encode(['referee_name', 'tournament_name', 'tournament_dates', 'assignment_role', 'club_name'])
            ],
            [
                'name' => 'institutional_report_standard',
                'type' => 'institutional',
                'subject' => 'Nuova Assegnazione - {{tournament_name}}',
                'body' => "Nuova assegnazione torneo comunicata:\n\n**TORNEO:** {{tournament_name}}\n**DATE:** {{tournament_dates}}\n**CIRCOLO:** {{club_name}}\n**ZONA:** {{zone_name}}\n\n**COMITATO DI GARA:**\n{{assignments_list}}\n\n---\nComunicazione automatica del sistema",
                'is_active' => true,
                'is_default' => true,
                'variables' => json_encode(['tournament_name', 'tournament_dates', 'club_name', 'zone_name', 'assignments_list'])
            ]
        ];

        foreach ($templates as $template) {
            LetterTemplate::updateOrCreate(
                ['name' => $template['name']],
                $template
            );
        }
    }
}
