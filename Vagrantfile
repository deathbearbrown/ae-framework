# -*- mode: ruby -*-
# vi: set ft=ruby :

VAGRANTFILE_API_VERSION = "2"

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|
  config.vm.box = "ubuntu/trusty64"
  config.vm.network "private_network", ip: "192.168.13.38"
  config.vm.synced_folder ".", "/mnt/ae-utils/"

  config.vm.provision "ansible" do |ansible|
    ansible.limit = "vagrant"
    ansible.inventory_path = "ansible/inventory/development"
    ansible.playbook = "ansible/provision.yml"
    ansible.raw_arguments = ['--timeout=30']
  end
end
