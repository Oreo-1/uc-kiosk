#include <SPI.h>
#include <PN532_SPI.h>
#include <PN532.h>
#include <NfcAdapter.h>

#define PN532_SS 5 // D1

PN532_SPI interface(SPI, PN532_SS);
NfcAdapter nfc = NfcAdapter(interface);

void setup() {

    Serial.begin(115200);
    delay(2000);

    Serial.println("NDEF Reader Started");

    nfc.begin();

    Serial.println("Waiting for NFC Tag...");
}

void loop() {

    if (nfc.tagPresent()) {

        NfcTag tag = nfc.read();

        Serial.println("\nTAG DETECTED");

        Serial.print("UID: ");
        Serial.println(tag.getUidString());

        if (tag.hasNdefMessage()) {

            NdefMessage message = tag.getNdefMessage();

            int recordCount = message.getRecordCount();

            Serial.print("Records: ");
            Serial.println(recordCount);

            for (int i = 0; i < recordCount; i++) {

                NdefRecord record = message.getRecord(i);

                int payloadLength = record.getPayloadLength();

                byte payload[payloadLength];

                record.getPayload(payload);

                String text = "";

                // Skip language code bytes
                for (int c = 3; c < payloadLength; c++) {
                    text += (char)payload[c];
                }

                Serial.print("Payload: ");
                Serial.println(text);
            }

        } else {

            Serial.println("No NDEF Message");
        }

        delay(3000);
    }
}