import Foundation
import Vision
import ImageIO
let bytes = FileHandle.standardInput.readDataToEndOfFile()
guard let source = CGImageSourceCreateWithData(bytes as CFData, nil), let image = CGImageSourceCreateImageAtIndex(source, 0, nil) else { exit(2) }
let request = VNDetectBarcodesRequest()
request.symbologies = [.qr]
try VNImageRequestHandler(cgImage:image, options:[:]).perform([request])
guard let value = request.results?.first?.payloadStringValue else { exit(3) }
FileHandle.standardOutput.write(Data(value.utf8))
