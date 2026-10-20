<?php
// Fonctions utilisées par plusieurs pages


// Renvoie « selected » si cette option du formulaire est celle qui a été choisie
function selectionne($valeur, $choix)
{
    if ($valeur == $choix) {
        return 'selected';
    }
    return '';
}


// Écrit une heure sur deux chiffres : 9 -> « 09h », 18 -> « 18h »
function afficherHeure($heure)
{
    if ($heure < 10) {
        return '0' . $heure . 'h';
    }
    return $heure . 'h';
}


// Affiche une requête SQL dans le panneau en bas de la page :
// son titre, le texte de la requête, la valeur donnée à chaque « ? » et le nombre de lignes trouvées
function afficherRequete($titre, $sql, $valeurs, $nb_lignes)
{
    echo "<article class='sql-card'>";
    echo "<header><strong>" . htmlspecialchars($titre) . "</strong>";
    echo "<span class='sql-meta'>$nb_lignes ligne(s)</span></header>";
    echo "<pre>" . htmlspecialchars($sql) . "</pre>";

    if (count($valeurs) == 0) {
        echo "<p class='no-param'>Aucun paramètre : requête fixe.</p>";
    } else {
        echo "<table class='params'>";
        echo "<thead><tr><th>Paramètre</th><th>Valeur envoyée</th></tr></thead>";
        echo "<tbody>";
        $numero = 1;
        foreach ($valeurs as $valeur) {
            echo "<tr><td><code>? n° $numero</code></td><td><code>" . htmlspecialchars($valeur) . "</code></td></tr>";
            $numero = $numero + 1;
        }
        echo "</tbody></table>";
    }

    echo "</article>";
}
