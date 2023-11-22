# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
  config.vm.box = "ubuntu/focal64"
  config.vm.provider "virtualbox" do |vb|
    vb.name = "Cacti"
    vb.memory = "2048"
  end
  config.vm.provision :shell, path: "bootstrap.sh"
end
# Vagrant.configure("2") do |config|
#   # Configuración básica
#   config.vm.box = "ubuntu/focal64"
#   # config.vm.network "forwarded_port", guest: 80, host: 8080
  
#   # Configuración de red con IP obtenida por DHCP
#   # config.vm.network "public_network", type: "dhcp"
  
#   # Configuración para permitir la conexión SSH
#   config.vm.provider "virtualbox" do |vb|
#   #   vb.customize ["modifyvm", :id, "--natdnshostresolver1", "on"]
#   # end
#   config.vm.provision :shell, path: "bootstrap.sh"
# end
