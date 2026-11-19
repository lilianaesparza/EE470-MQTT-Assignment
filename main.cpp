
#include <Arduino.h>
#include <ESP8266WiFi.h>
#include <PubSubClient.h>
#include <string.h>
#include <ctype.h>

// ------------------------------------
// Wi-Fi Hotspot
// ------------------------------------
const char* ssid = "YOUR_WIFI_NAME";
const char* password = "YOUR_WIFI_PASSWORD";

// ------------------------------------
// MQTT Broker
// ------------------------------------
const char* mqtt_server = "broker.hivemq.com";
const int mqtt_port = 1883;

// MQTT Topics
const char* publishTopic =
    "testtopic/temp/outTopic/esparza";

const char* subscribeTopic =
    "testtopic/temp/inTopic/esparza";

const char* buttonTopic =
    "testtopic/button/outTopic/esparza";

const char* ledStatusTopic =
    "testtopic/led/status/esparza";

// ------------------------------------
// Hardware Pins
// ------------------------------------
#define LED_PIN D2
#define POT_PIN A0
#define BUTTON_PIN D5

// ------------------------------------
// MQTT Client
// ------------------------------------
WiFiClient espClient;
PubSubClient client(espClient);

// ------------------------------------
// Timing
// ------------------------------------
unsigned long lastPublish = 0;
unsigned long lastPrint = 0;

const unsigned long publishInterval = 15000;
const unsigned long printInterval = 500;
const unsigned long buttonReleaseInterval = 5000;

// Button variables
bool lastButtonReading = HIGH;
bool stableButtonState = HIGH;

unsigned long lastDebounceTime = 0;
const unsigned long debounceDelay = 50;

bool buttonActive = false;
unsigned long buttonPublishTime = 0;

// LED state
bool ledState = false;

// ------------------------------------
// Connect to Wi-Fi
// ------------------------------------
void setup_wifi() {

  Serial.print("Connecting to WiFi");

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.println("WiFi Connected!");

  Serial.print("IP Address: ");
  Serial.println(WiFi.localIP());
}

// ------------------------------------
// Publish LED Status
// ------------------------------------
void publishLEDStatus() {

  if (client.connected()) {

    if (client.publish(
        ledStatusTopic,
        ledState ? "1" : "0",
        true)) {

      Serial.print("LED Status Published: ");
      Serial.println(ledState ? "1" : "0");

    } else {

      Serial.println("LED Status Publish Failed");
    }
  }
}

// ------------------------------------
// Update LED and Publish Status
// ------------------------------------
void updateLED(bool turnOn) {

  ledState = turnOn;

  digitalWrite(
      LED_PIN,
      ledState ? HIGH : LOW
  );

  Serial.println(
      ledState ? "LED ON" : "LED OFF"
  );

  publishLEDStatus();
}

// ------------------------------------
// MQTT Callback - Receive Messages
// ------------------------------------
void callback(char* topic, byte* payload,
              unsigned int length) {

  Serial.print("Message received on ");
  Serial.print(topic);
  Serial.print(": ");

  for (unsigned int i = 0; i < length; i++) {
    Serial.print((char)payload[i]);
  }

  Serial.println();

  // Only process LED control messages
  if (strcmp(topic, subscribeTopic) != 0) {
    return;
  }

  // Remove optional whitespace
  unsigned int start = 0;
  unsigned int end = length;

  while (start < end &&
         isspace((unsigned char)payload[start])) {
    start++;
  }

  while (end > start &&
         isspace((unsigned char)payload[end - 1])) {
    end--;
  }

  // Require one command character
  if (end - start != 1) {
    Serial.println("Invalid LED command");
    return;
  }

  if (payload[start] == '1') {
    updateLED(true);
  }
  else if (payload[start] == '0') {
    updateLED(false);
  }
  else {
    Serial.println("Invalid LED command");
  }
}

// ------------------------------------
// Connect to MQTT Broker
// ------------------------------------
void reconnect() {

  while (!client.connected()) {

    Serial.print("Connecting to HiveMQ...");

    String clientId = "ESP8266-";
    clientId += String(ESP.getChipId(), HEX);

    if (client.connect(clientId.c_str())) {

      Serial.println("Connected!");

      if (client.subscribe(subscribeTopic)) {

        Serial.print("Subscribed to: ");
        Serial.println(subscribeTopic);

      } else {

        Serial.println("Subscription failed");
      }

      // Publish initial/current LED state
      publishLEDStatus();

    } else {

      Serial.print("Failed, state=");
      Serial.println(client.state());

      delay(5000);
    }
  }
}

// ------------------------------------
// Setup
// ------------------------------------
void setup() {

  Serial.begin(115200);

  // LED setup
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW);
  ledState = false;

  // Button setup
  pinMode(BUTTON_PIN, INPUT_PULLUP);

  // Connect to Wi-Fi
  setup_wifi();

  // Configure MQTT
  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(callback);

  Serial.println("MQTT Assignment - Section 4.2G");
}

// ------------------------------------
// Main Loop
// ------------------------------------
void loop() {

  // Maintain Wi-Fi
  if (WiFi.status() != WL_CONNECTED) {
    setup_wifi();
  }

  // Maintain MQTT
  if (!client.connected()) {
    reconnect();
  }

  client.loop();

  unsigned long now = millis();

  // --------------------------------
  // Display Potentiometer
  // --------------------------------
  if (now - lastPrint >= printInterval) {

    lastPrint = now;

    int potValue = analogRead(POT_PIN);

    Serial.print("Potentiometer: ");
    Serial.println(potValue);
  }

  // --------------------------------
  // Publish Potentiometer
  // Every 15 seconds
  // --------------------------------
  if (now - lastPublish >= publishInterval) {

    lastPublish = now;

    int potValue = analogRead(POT_PIN);
    String message = String(potValue);

    if (client.publish(
        publishTopic,
        message.c_str())) {

      Serial.print("MQTT Potentiometer Published: ");
      Serial.println(message);

    } else {

      Serial.println("MQTT Publish Failed");
    }
  }

  // --------------------------------
  // Push Button with Debouncing
  // --------------------------------
  bool reading = digitalRead(BUTTON_PIN);

  if (reading != lastButtonReading) {
    lastDebounceTime = now;
  }

  if (now - lastDebounceTime >= debounceDelay) {

    if (reading != stableButtonState) {

      stableButtonState = reading;

      // Button pressed
      if (stableButtonState == LOW &&
          !buttonActive) {

        Serial.println("Button Pressed: 1");

        if (client.publish(buttonTopic, "1")) {

          Serial.println("MQTT Button Published: 1");

          buttonActive = true;
          buttonPublishTime = now;

        } else {

          Serial.println("Button Publish Failed");
        }
      }
    }
  }

  lastButtonReading = reading;

  // --------------------------------
  // Publish 0 After 5 Seconds
  // --------------------------------
  if (buttonActive &&
      now - buttonPublishTime >=
          buttonReleaseInterval) {

    if (client.publish(buttonTopic, "0")) {

      Serial.println("MQTT Button Published: 0");

      buttonActive = false;

    } else {

      Serial.println("Button Reset Publish Failed");
    }
  }

  // --------------------------------
  // LED Control via Serial Monitor
  // --------------------------------
  if (Serial.available() > 0) {

    char input = Serial.read();

    if (input == '1') {

      updateLED(true);
      Serial.println("LED ON via Serial");

    }
    else if (input == '0') {

      updateLED(false);
      Serial.println("LED OFF via Serial");
    }
  }

  delay(10);
}
