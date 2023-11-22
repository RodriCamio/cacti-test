#!/bin/bash

# DOCKER INSTALL
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh --dry-run
sudo sh get-docker.sh
# USE DOCKER WITHOUT SUDO
sudo groupadd docker
sudo usermod -aG docker vagrant
newgrp docker

# INSTALL KUBECTL
curl -LO "https://dl.k8s.io/release/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl"
sudo install -o root -g root -m 0755 kubectl /usr/local/bin/kubectl

# INSTALL MINIKUBE
curl -LO https://storage.googleapis.com/minikube/releases/latest/minikube-linux-amd64
sudo install minikube-linux-amd64 /usr/local/bin/minikube

# #!/bin/bash

# export DEBIAN_FRONTEND=noninteractive

# # Establecer las selecciones predeterminadas para debconf
# sudo debconf-set-selections <<< 'phpmyadmin phpmyadmin/dbconfig-install boolean true'
# sudo debconf-set-selections <<< 'phpmyadmin phpmyadmin/mysql/admin-pass password cacti'
# sudo debconf-set-selections <<< 'phpmyadmin phpmyadmin/mysql/app-pass password cacti'
# sudo debconf-set-selections <<< 'phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2'

# # Instalar phpmyadmin y apache2
sudo apt-get update
# sudo apt-get install -y apache2 php phpmyadmin mysql-server

# # Establecer las selecciones predeterminadas para debconf
# sudo debconf-set-selections <<< 'cacti cacti/dbconfig-install boolean true'
# sudo debconf-set-selections <<< 'cacti cacti/mysql/admin-pass password cacti'
# sudo debconf-set-selections <<< 'cacti cacti/mysql/app-pass password cacti'
# sudo debconf-set-selections <<< 'cacti cacti/reconfigure-webserver multiselect apache2'

# # # Establecer las selecciones predeterminadas para spine (si es necesario)
# # sudo debconf-set-selections <<< 'cacti-spine cacti-spine/dbconfig-install boolean true'
# # sudo debconf-set-selections <<< 'cacti-spine cacti-spine/mysql/admin-pass password cacti'
# # sudo debconf-set-selections <<< 'cacti-spine cacti-spine/mysql/app-pass password cacti'

# # Instalar cacti y cacti-spine
# sudo apt-get update
# sudo apt-get install -y cacti 

# # Reiniciar servicios
# sudo service apache2 restart
# sudo service mysql restart
# sudo service spine restart
# sudo service cron restart