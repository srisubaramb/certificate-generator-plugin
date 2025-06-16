# 🎓 Certificate Generator Plugin

A lightweight PHP plugin to dynamically generate personalized certificates with embedded QR codes. Perfect for event organizers, educators, and online platforms.

![License](https://img.shields.io/github/license/srisubaramb/certificate-generator-plugin)
![PHP](https://img.shields.io/badge/PHP-7.4+-blue)
![Status](https://img.shields.io/badge/status-active-brightgreen)

---

## 📌 Features

- 🖼️ Custom certificate design using an image template
- 📝 Dynamic name input
- 🔡 Custom font rendering (`Poppins`)
- 🔳 Embedded QR code using `phpqrcode` library
- 📤 Downloadable certificate in image format

---

## 🚀 Getting Started

### 📁 Installation

1. Clone or download this repository:

```bash
git clone https://github.com/srisubaramb/certificate-generator-plugin.git
```

2. Place the `certificate-generator` folder in your server's root directory (e.g., `htdocs` or `www`).

3. Make sure your PHP version is 7.4+.

### 🌐 Usage

1. Open your browser and navigate to:

```
http://localhost/certificate-generator/
```
2. Add the template(certificate-template.png) into the root

3. Enter your name in the input field and generate your certificate.

---

## 🧰 Tech Stack

- PHP
- GD Library (for image manipulation)
- `phpqrcode` (QR code generation)
- HTML/CSS (minimal frontend)

---

## 📂 Folder Structure

```
certificate-generator/
├── certificate-generator.php     # Main logic file
├── certificate-template.jpg      # Background template for the certificate
├── Poppins-Regular.ttf           # Font used for name text
└── phpqrcode/                    # QR code generation library
```

---

## 📸 Preview

> _(Add a screenshot of the certificate here if possible)_  
> ![Preview]()

---

## 📝 License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---

## 🙌 Acknowledgements

- [phpqrcode](http://phpqrcode.sourceforge.net/) for QR code support
- Open source fonts from [Google Fonts](https://fonts.google.com/)

---

## ✨ Author

Developed by [@srisubaramb](https://github.com/srisubaramb)

---

## 💡 Future Improvements

- PDF certificate download
- Admin panel for bulk generation
- Email delivery integration
