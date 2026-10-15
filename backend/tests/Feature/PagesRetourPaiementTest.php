<?php

describe('pages de retour de paiement', function () {
    it('affiche la page de succès, publique et sans donnée sensible', function () {
        $reponse = $this->get('/paiement/succes?reference=SOPI-PAY-123&montant=3000&telephone=771234567&nom=<script>x</script>');

        $reponse->assertOk()
            ->assertSee('Paiement reçu')
            ->assertSee('Votre acompte a bien été reçu. Retournez dans l\'application Sopi pour voir votre réservation.')
            ->assertDontSee('SOPI-PAY-123')
            ->assertDontSee('3000')
            ->assertDontSee('771234567')
            ->assertDontSee('<script>x</script>', false);
    });

    it('affiche la page d\'échec, publique et sans donnée sensible', function () {
        $reponse = $this->get('/paiement/echec?reference=SOPI-PAY-123&montant=3000&telephone=771234567&nom=<script>x</script>');

        $reponse->assertOk()
            ->assertSee('Paiement non abouti')
            ->assertSee('Le paiement n\'a pas abouti. Aucun montant ne vous sera prélevé. Retournez dans l\'application Sopi pour réessayer.')
            ->assertDontSee('SOPI-PAY-123')
            ->assertDontSee('3000')
            ->assertDontSee('771234567')
            ->assertDontSee('<script>x</script>', false);
    });
});
