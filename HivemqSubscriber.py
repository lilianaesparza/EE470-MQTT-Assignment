
# EE470 MQTT Assignment - Section 4.2F2
# MQTT Subscriber and Hostinger Database Relay
# Based on Professor Farahmand's Appendix A.3 and A.4

import paho.mqtt.client as mqtt
import mysql.connector
from getpass import getpass

# ------------------------------------
# MQTT Broker Settings
# ------------------------------------
BROKER_URL = "broker.hivemq.com"
BROKER_PORT = 1883
TOPIC = "testtopic/temp/outTopic/esparza"

# ------------------------------------
# Hostinger Database Settings
# ------------------------------------
HOST = "srv866.hstgr.io"
PORT = 3306
USER = "u553872955_db_LilianaEsp"
DATABASE = "u553872955_LilianaEsparza"

# Enter password privately when starting
PASSWORD = getpass("Enter MySQL database password: ")

# ------------------------------------
# Insert Data into Hostinger
# ------------------------------------
def push_value_to_db(sensor_value):

    connection = None
    cursor = None

    try:
        connection = mysql.connector.connect(
            host=HOST,
            port=PORT,
            user=USER,
            password=PASSWORD,
            database=DATABASE,
            connection_timeout=10,
            force_ipv6=False,
            use_pure=True
        )

        cursor = connection.cursor()

        query = """
            INSERT INTO mqtt_sensor_data
            (potentiometer_value)
            VALUES (%s)
        """

        cursor.execute(query, (sensor_value,))
        connection.commit()

        print("SUCCESS! Value inserted into Hostinger:",
              sensor_value)

    except mysql.connector.Error as error:
        print("Database error:", error)

    finally:
        if cursor is not None:
            cursor.close()

        if connection is not None and connection.is_connected():
            connection.close()


# ------------------------------------
# MQTT Connection Callback
# ------------------------------------
def on_connect(client, userdata, flags,
               reason_code, properties):

    if reason_code == 0:
        print("Connected to HiveMQ broker!")

        client.subscribe(TOPIC)
        print("Subscribed to:", TOPIC)

    else:
        print("MQTT connection failed:", reason_code)


# ------------------------------------
# MQTT Message Callback
# ------------------------------------
def on_message(client, userdata, msg):

    try:
        value = int(msg.payload.decode("utf-8").strip())

        if not 0 <= value <= 1023:
            print("Invalid potentiometer value:", value)
            return

        print("-----------------------------")
        print("Potentiometer received:", value)
        print("Topic:", msg.topic)

        push_value_to_db(value)

    except ValueError:
        print("Invalid MQTT payload:", repr(msg.payload))


# ------------------------------------
# Main Program
# ------------------------------------
print("Starting MQTT-to-Hostinger relay...")

client = mqtt.Client(
    callback_api_version=mqtt.CallbackAPIVersion.VERSION2
)

client.on_connect = on_connect
client.on_message = on_message

print("Connecting to HiveMQ...")

client.connect(BROKER_URL, BROKER_PORT, 60)

client.loop_forever()
