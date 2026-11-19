# EE470 MQTT Assignment

## Project Overview

For this assignment, I used an ESP8266 NodeMCU to
communicate with the HiveMQ MQTT broker. The ESP8266
reads a potentiometer, controls an LED, and detects
when a push button is pressed.

I also used the EasyMQTT application on my iPhone
to publish and subscribe to MQTT messages.

For the database portion, I created a Python relay
that receives potentiometer readings from HiveMQ
and inserts them into my Hostinger MySQL database.
I then created a webpage to display the readings
and their timestamps in a graph.

## Hardware Used

- ESP8266 NodeMCU
- 3296 potentiometer
- Push button
- LED
- 220–330 ohm resistor
- Breadboard and jumper wires

## Software Used

- Visual Studio Code
- PlatformIO
- Arduino Framework
- PubSubClient MQTT Library
- HiveMQ MQTT Broker
- EasyMQTT iPhone Application
- Python
- Paho MQTT
- MySQL Connector
- Hostinger MySQL Database
- PHP and Chart.js

## MQTT Configuration

Broker: broker.hivemq.com

Port: 1883

Publishing interval: 15 seconds

### MQTT Topics

Potentiometer:
testtopic/temp/outTopic/esparza

LED Control:
testtopic/temp/inTopic/esparza

Push Button:
testtopic/button/outTopic/esparza

LED Status:
testtopic/led/status/esparza

## Project Functionality

### Potentiometer

The ESP8266 reads the potentiometer through A0
and publishes its value to HiveMQ every 15 seconds.

### LED Control

The ESP8266 subscribes to the LED control topic.

Publishing 1 turns the LED ON.

Publishing 0 turns the LED OFF.

The ESP8266 also publishes its LED status so
the subscriber can monitor whether it is ON or OFF.

### Push Button

When the push button is pressed, the ESP8266
publishes 1 to HiveMQ.

After five seconds, it publishes 0.

### Python Relay

The Python program subscribes to the potentiometer
topic and receives the sensor readings from HiveMQ.

It then inserts the values into the Hostinger
MySQL database.

If the Python program stops running, new readings
are no longer inserted into the database.

### MQTT Dashboard

I created a PHP webpage that displays the
potentiometer readings stored in MySQL.

The webpage includes:
- Latest potentiometer value
- Total stored readings
- Line graph of sensor measurements
- Timestamps displayed in Pacific Time
- Table of stored readings

## Project Files

main.cpp
ESP8266 MQTT publishing, subscribing, LED control,
and sensor reading program.

platformio.ini
PlatformIO board and library configuration.

HivemqSubscriber.py
Python MQTT subscriber and MySQL database relay.

mqtt_graph.php
PHP webpage for displaying MQTT sensor readings.

## Website

MQTT Dashboard:
https://snow-squirrel-383242.hostingersite.com/mqtt_graph.php

## Notes

The ESP8266 uses Wi-Fi to connect to the MQTT broker.
The MQTT broker forwards messages between publishers
and subscribers.

The Python relay is responsible for transferring
potentiometer readings from HiveMQ into MySQL.

Database credentials and Wi-Fi passwords are not
included in this repository.
