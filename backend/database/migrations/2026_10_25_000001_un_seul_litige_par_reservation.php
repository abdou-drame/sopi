<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Diagramme de classes (relation « engendrer ») : une réservation engendre au plus un litige, clôturé ou non.
        // L'index unique partiel (litiges ouverts seulement) devient un index unique simple sur reservation_id.
        // S'il existe déjà des doublons, on s'arrête AVANT toute modification, en les listant : ils doivent être traités à la main.
        $doublons = DB::table('litiges')
            ->select('reservation_id', DB::raw('count(*) as nombre'))
            ->groupBy('reservation_id')
            ->havingRaw('count(*) > 1')
            ->orderBy('reservation_id')
            ->get();

        if ($doublons->isNotEmpty()) {
            $liste = $doublons->map(fn ($d) => "{$d->reservation_id} ({$d->nombre} litiges)")->implode(', ');

            throw new RuntimeException("Migration interrompue, aucune modification effectuée : plusieurs litiges existent pour une même réservation. Réservations concernées : {$liste}. Clôturez ou supprimez les litiges en trop, puis relancez la migration.");
        }

        DB::statement('DROP INDEX litiges_un_ouvert_par_reservation');
        DB::statement('CREATE UNIQUE INDEX litiges_reservation_unique ON litiges (reservation_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX litiges_reservation_unique');
        DB::statement("CREATE UNIQUE INDEX litiges_un_ouvert_par_reservation ON litiges (reservation_id) WHERE statut = 'ouvert'");
    }
};
