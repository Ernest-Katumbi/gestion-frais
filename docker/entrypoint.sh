#!/bin/sh
# Démarrage du conteneur :
#  1. Apache écoute sur le port imposé par l'hébergeur ($PORT, 10000 par défaut sur Render) ;
#  2. le certificat de l'autorité de la base (DB_SSL_CA_PEM) est écrit dans un fichier ;
#  3. au premier démarrage, la base vide est installée avec les données de démonstration
#     (en arrière-plan, pour qu'Apache réponde tout de suite) ;
#  4. Apache est lancé.
set -e

PORT="${PORT:-10000}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf

# Certificat CA de la base MySQL fourni en texte (variable d'environnement) → fichier.
if [ -n "${DB_SSL_CA_PEM:-}" ]; then
    printf '%s\n' "$DB_SSL_CA_PEM" > /tmp/db-ca.pem
    export DB_SSL_CA=/tmp/db-ca.pem
fi

# Les variables d'environnement (DB_*, secrets…) sont héritées par Apache et lues par PHP (getenv).

# Installation des données de démonstration si la base est vide (ne touche jamais une base existante).
# Exécutée sous l'utilisateur d'Apache pour que les journaux restent inscriptibles par l'application.
if [ "${AUTO_SEED:-true}" = "true" ]; then
    (
        sleep 2
        su -p -s /bin/sh www-data -c "/usr/local/bin/php /var/www/html/database/seed.php --si-vide --sans-pdf" \
            && echo "[gestion-frais] Base prête." \
            || echo "[gestion-frais] Installation de la base impossible : vérifiez les variables DB_*."
    ) &
fi

exec "$@"
