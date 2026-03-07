#!/bin/bash
# MySQL → S3 backup script for saramagdits.com
#
# Install on EC2:
#   sudo cp drupal-backup.sh /usr/local/bin/drupal-backup.sh
#   sudo chmod +x /usr/local/bin/drupal-backup.sh
#
# Add to crontab (daily at 2am):
#   echo "0 2 * * * root /usr/local/bin/drupal-backup.sh" | sudo tee /etc/cron.d/drupal-backup
#
# The S3 bucket lifecycle rule (set in CDK) deletes backups older than 30 days.

set -euo pipefail

DB_NAME="drupaldb"
DB_USER="drupal"
# Read password from environment or a secrets file — never hardcode in scripts.
# Set this in /etc/environment or use AWS Secrets Manager.
DB_PASS="${DRUPAL_DB_PASSWORD:?DRUPAL_DB_PASSWORD environment variable is not set}"

BUCKET="saramagdits-db-backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
TMPFILE="/tmp/drupal_${TIMESTAMP}.sql.gz"

echo "[$(date)] Starting backup of ${DB_NAME}..."

mysqldump \
  --user="${DB_USER}" \
  --password="${DB_PASS}" \
  --single-transaction \
  --routines \
  --triggers \
  --host=localhost \
  "${DB_NAME}" | gzip > "${TMPFILE}"

echo "[$(date)] Uploading to s3://${BUCKET}/mysql/${TIMESTAMP}.sql.gz ..."

aws s3 cp "${TMPFILE}" "s3://${BUCKET}/mysql/${TIMESTAMP}.sql.gz" \
  --storage-class STANDARD_IA

rm -f "${TMPFILE}"

echo "[$(date)] Backup complete."
