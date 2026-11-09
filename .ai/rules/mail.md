---
paths:
  - 'resources/views/mail/**'
---

# Mail

## Emails are plain notes with a text twin
Every email extends `mail.layout` (white, one 560px column, system font stack, no card, no images, no web fonts) and is sent with `->view(['mail.x', 'mail.x-text'], $data)` — always a plain-text twin. Not Laravel's markdown mail theme. Styles are inline (Gmail/Outlook drop most of <style>); spacing is an 8px rhythm: 16px text on 26px lines, 16px between paragraphs, 32px between sections, 40px above the footer. Keep "Source" links glued to their sentence with `&nbsp;`, and show the sign-in code split by a margin, not a space, so it copies as six digits.
