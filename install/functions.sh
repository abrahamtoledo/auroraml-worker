#!/bin/bash

read_def (){
    # Use: read_def <prompt> <default>
    prompt="$1"
    default="$2"

    read -p "$prompt [$default]: " var
    test -z $var && var=$default

    echo "$var"
}