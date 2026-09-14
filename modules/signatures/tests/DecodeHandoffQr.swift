// macOS QA only: compare locally decoded QR against a URL supplied via stdin.
import Foundation
import Vision
let url = URL(fileURLWithPath: CommandLine.arguments[1])
let expected = String(data: FileHandle.standardInput.readDataToEndOfFile(), encoding: .utf8)!.trimmingCharacters(in: .whitespacesAndNewlines)
let request = VNDetectBarcodesRequest()
request.symbologies = [.qr]
try VNImageRequestHandler(url: url).perform([request])
let matched = request.results?.contains(where: { $0.payloadStringValue == expected }) ?? false
print(matched ? "QR_DECODE_MATCH=PASS" : "QR_DECODE_MATCH=FAIL")
exit(matched ? 0 : 1)
