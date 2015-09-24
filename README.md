# ae-utils [![Build Status](https://travis-ci.org/wfmu/ae-utils.svg?branch=master)](https://travis-ci.org/wfmu/ae-utils)

## Getting Started

To work on this project, first install [Vagrant](https://www.vagrantup.com/)
and [Ansible](http://www.ansible.com/home). Then, run `vagrant up` from the
project root. This will provision a virtual machine that contains all the
necessary dependencies for executing the code in this project. Run the shell
command `vagrant ssh` to establish a shell connection to the virtual machine.

## Project Tests

Execute the following command from the project root to run the automated tests:

    make test
