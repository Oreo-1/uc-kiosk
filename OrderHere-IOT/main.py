import threading
import time

from flask import Flask, jsonify
import board
import busio
from digitalio import DigitalInOut
from adafruit_pn532.spi import PN532_SPI

# ==========================================
# Flask
# ==========================================
app = Flask(__name__)

latest_user_id = None
user_event = threading.Event()

# ==========================================
# PN532
# ==========================================
spi = busio.SPI(board.SCK, board.MOSI, board.MISO)
cs_pin = DigitalInOut(board.D5)

pn532 = PN532_SPI(spi, cs_pin, debug=False)


def setup():
    print("====== PN532 HCE READER ======")

    try:
        ic, ver, rev, support = pn532.firmware_version
        print(f"Found chip PN5{ic:x}")
    except Exception as e:
        print("PN532 NOT FOUND.", e)
        exit(1)

    pn532.SAM_configuration()

    print("WAITING FOR PHONE...")


@app.route("/wait-user", methods=["GET"])
def wait_user():
    """
    Long polling endpoint.
    Akan menunggu maksimal 30 detik.
    """

    detected = user_event.wait(timeout=30)

    if not detected:
        return jsonify({
            "success": False,
            "message": "timeout"
        }), 408

    global latest_user_id

    user_id = latest_user_id

    # reset supaya request berikutnya menunggu lagi
    user_event.clear()

    return jsonify({
        "success": True,
        "userId": user_id
    })


def nfc_loop():
    global latest_user_id

    while True:
        uid = pn532.read_passive_target(timeout=0.5)

        if uid is None:
            continue

        print(f"[!] Terdeteksi UID: {[hex(i) for i in uid]}")

        select_apdu = bytearray([
            0x00, 0xA4, 0x04, 0x00, 0x07,
            0xF0, 0x01, 0x02, 0x03,
            0x04, 0x05, 0x06, 0x00
        ])

        params = bytearray([0x01]) + select_apdu

        try:
            response = pn532.call_function(
                0x40,
                response_length=64,
                params=params
            )

            if response and response[0] == 0x00:

                data_payload = response[1:-2]
                user_id = bytes(data_payload).decode(
                    "ascii",
                    errors="replace"
                )

                print(f"[DATA] {user_id}")

                latest_user_id = user_id

                # bangunkan semua client yang sedang menunggu
                user_event.set()

            else:
                print("[WARN] Response tidak OK")

        except Exception as e:
            print(f"[ERR] APDU gagal: {e}")

        time.sleep(2)


if __name__ == "__main__":
    setup()

    threading.Thread(
        target=nfc_loop,
        daemon=True
    ).start()

    app.run(
        host="0.0.0.0",
        port=5000,
        threaded=True
    )