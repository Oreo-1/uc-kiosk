#include <SPI.h>
#include <PN532_SPI.h>
#include <PN532.h>

#define PN532_SS 5

PN532_SPI pn532spi(SPI, PN532_SS);
PN532 nfc(pn532spi);

void setup() {

  Serial.begin(115200);

  delay(2000);

  Serial.println("====== PN532 HCE READER ======");

  nfc.begin();

  uint32_t versiondata = nfc.getFirmwareVersion();

  if (!versiondata) {

    Serial.println("PN532 NOT FOUND");

    while (1);
  }

  Serial.print("Found chip PN5");
  Serial.println((versiondata >> 24) & 0xFF, HEX);

  Serial.print("Firmware ver. ");
  Serial.print((versiondata >> 16) & 0xFF, DEC);

  Serial.print('.');
  Serial.println((versiondata >> 8) & 0xFF, DEC);

  nfc.SAMConfig();

  Serial.println("WAITING FOR PHONE...");
}

void loop() {

  bool success;

  uint8_t response[64];
  uint8_t responseLength = sizeof(response);

  Serial.println("\nSearching for phone...");

  // Better for HCE than readPassiveTargetID()
  success = nfc.inListPassiveTarget();

  if (success) {

    Serial.println("PHONE DETECTED");

    // SELECT AID COMMAND
    uint8_t selectApdu[] = {
      0x00,
      0xA4,
      0x04,
      0x00,
      0x07,
      0xF0,
      0x01,
      0x02,
      0x03,
      0x04,
      0x05,
      0x06,
      0x00
    };

    success = nfc.inDataExchange(
      selectApdu,
      sizeof(selectApdu),
      response,
      &responseLength
    );

    if (success) {

      Serial.println("\n=== RAW RESPONSE ===");

      nfc.PrintHexChar(response, responseLength);

      Serial.println("\n=== USER ID ===");

      // Ignore last 2 bytes (90 00)
      for (int i = 0; i < responseLength - 2; i++) {

        Serial.print((char)response[i]);
      }

      Serial.println();

    } else {

      Serial.println("FAILED SENDING SELECT AID");
    }

  } else {

    Serial.println("NO PHONE DETECTED");
  }

  delay(2000);
}