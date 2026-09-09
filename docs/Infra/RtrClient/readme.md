# RtrClient module

## introduction

Module for the Real Time Registry REST api as a proxy client to the sandwave-io/realtimeregister-php package

#### Documenation
[Api documentation](https://dm.realtimeregister.com/docs/api/)

[Github Realtime Register package](https://github.com/sandwave-io/realtimeregister-php)


#### Contact
Developer bij Realtime register: wiebren.braakman@realtimeregister.com

Some important emails

Bij registeren:

```
Hoi Wiebren,

Ik heb wat vragen over register van het domain.
Wat is het verschil tussen registerent en customer bij het registeren?
Wat is de customer is dat de customer waar je ook mee zou inloggen.
Wat is een registerent zijn wij dit of is dit onze klant?
Bij het registeren in de docs staat dat contacts optioneel is maar ik krijg dit : ```Nov 12 17:29:22 [2020-11-12 17:29:22] production.DEBUG: RealtimeRegister.RESPONSE: 400 - BODY: {"message":"There should be exactly 1 admin contact","type":"ValidationError"} {"response_code":400,"response_body":"{\"message\":\"There should be exactly 1 admin contact\",\"type\":\"ValidationError\"}","expected_response":"NOT SET","headers":{"Server":["nginx/1.14.0 (Ubuntu)"],"Date":["Thu, 12 Nov 2020 16:29:22 GMT"],"Content-Type":["application/json;charset=UTF-8"],"Content-Length":["78"],"Connection":["keep-alive"],"x-process-id":["44614550"]}}```

Dat ik 1 admin contact nodig heb dus ik denk niet dat die optioneel is.

Met vriendelijke groet,
Thomas



Hoi,

De customer is de klant bij ons, b.v. Versio. De registrant is de eigenaar van het domein, b.v. een klant van Versio.

De requirements voor het aantal contacten verschilt per registry, dit staat in de metadata, zie ook het overzicht op https://dm.realtimeregister.com/docs/api/tlds/metadata. Het meest gebruikelijk is om altijd 1 admin, 1 billing, 1 tech contact op te geven, dat wordt bij alle registries geaccepteerd.

Best regards,

Wiebren Braakman
Chief Software Architect

```
