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

