echo "Sync [ bulk-print-consignment ] to [ loveandscone.com ]"
rsync -avzh --chown="www-data:www-data" \
  --exclude=deploy.sh \
  --exclude=logs \
  --exclude=saves \
  --exclude=*.swp \
  --exclude=.git* \
  . /opt/easyengine/sites/loveandscone.com/app/htdocs/wp-content/plugins/bulk-print-consignment

echo "Sync [ bulk-print-consignment ] to [ loveandsecret.com ]"
rsync -avzh --chown="www-data:www-data" \
  --exclude=deploy.sh \
  --exclude=logs \
  --exclude=saves \
  --exclude=*.swp \
  --exclude=.git* \
  . /opt/easyengine/sites/loveandsecret.com/app/htdocs/wp-content/plugins/bulk-print-consignment
