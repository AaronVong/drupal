# Development Environment
* PHP 8.1

# Docker backup and restore db
# Backup
docker exec db_container_name /usr/bin/mysqldump -u admin --password=123456 db_name > director/to/db.sql

# Restore
cat director/to/db.sql | docker exec -i db_container_name /usr/bin/mysql -u admin --password=123456 db_name

# Docker access
docker exec -it plb bash

# Kubernetes - Setup
1. Have AWS CLI installed<br>
   https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html

2. Configure AWS CLI with your Access Key ID and Access Key Password
   *If you have multiple AWS projects going on use AWS profiles <br>
   https://docs.aws.amazon.com/cli/latest/userguide/cli-configure-profiles.html

3. Run the command on your terminal. <br>
   aws eks --region ap-southeast-1 update-kubeconfig --name preprod-EKS-cluster <br>
   kubectl get svc <br>
   kubectl get pods <br>
   kubectl exec --stdin --tty [podname] -- /bin/bash

4. Setup s3 bucket
   https://www.tothenew.com/blog/s3-bucket-configuration-with-drupal-8/