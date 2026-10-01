// Session 3 starter: the two validators to paste (they are long; the rest of the session is typed).
// Open the shell on the sandbox copy first:  docker compose exec mongodb mongosh sandbox

// Validation step 1: the v1 validator
// a validator on an existing collection: what a villager must look like from now on
db.runCommand({ collMod: "villagers", validator: { $jsonSchema: {
  bsonType: "object",
  required: ["name", "species", "personality", "birthday", "furniture"],
  properties: {
    name:        { bsonType: "string", minLength: 1 },
    species:     { bsonType: "string" },
    personality: { enum: ["Lazy", "Normal", "Cranky", "Snooty", "Jock", "Peppy", "Smug", "Big Sister"] },
    birthday:    { bsonType: "object", required: ["month", "day"],
                   properties: { month: { bsonType: "int", minimum: 1, maximum: 12 },
                                 day:   { bsonType: "int", minimum: 1, maximum: 31 } } },
    furniture:   { bsonType: "array", items: { bsonType: "int" }, maxItems: 20 }
  }
} }, validationLevel: "strict", validationAction: "error" })

// Validation step 6: the validator that accepts v1 and v2 during the migration
// the validator must allow both versions while the migration runs
db.runCommand({ collMod: "villagers", validator: { $jsonSchema: {
  bsonType: "object",
  required: ["name", "species", "personality", "birthday", "furniture"],
  properties: {
    name:        { bsonType: "string", minLength: 1 },
    personality: { enum: ["Lazy", "Normal", "Cranky", "Snooty", "Jock", "Peppy", "Smug", "Big Sister"] },
    furniture:   { bsonType: "array", items: { bsonType: "int" }, maxItems: 20 }
  },
  oneOf: [
    { not: { required: ["schemaVersion"] }, properties: { birthday: { bsonType: "object" } } },
    { required: ["schemaVersion"],
      properties: { schemaVersion: { enum: [2] },
                    birthday: { bsonType: "string", pattern: "^[0-9]{1,2}-[0-9]{1,2}$" } } }
  ]
} } })
