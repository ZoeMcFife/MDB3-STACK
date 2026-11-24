// Session 3, part 3: schema validation and evolution, on a sandbox copy of the villagers
// Run in:  docker compose exec mongodb mongosh sandbox   (the copy from the index part is reused)

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

// one that passes, one that fails: read the error, it names the field and the rule
db.villagers.insertOne({ name: "Tom Nook", species: "Raccoon", personality: "Smug",
                         birthday: { month: 5, day: 30 }, furniture: [717] })
db.villagers.insertOne({ name: "Nobody", species: "Cat", personality: "Grumpy",
                         birthday: { month: 13, day: 1 }, furniture: ["717"] })

// updates are validated too, and a bad document already in the collection stays until it is touched
db.villagers.updateOne({ name: "Tom Nook" }, { $set: { personality: "Grumpy" } })
db.villagers.updateOne({ name: "Tom Nook" }, { $set: { catchphrase: "yes, yes" } })

// which existing documents fail the validator? The schema is a query operator too.
const schema = db.getCollectionInfos({ name: "villagers" })[0].options.validator.$jsonSchema
db.villagers.find({ $nor: [ { $jsonSchema: schema } ] }, { name: 1, _id: 0 })
db.villagers.updateOne({ name: "Julian" }, { $set: { nickname: "Jules" } })
db.runCommand({ collMod: "villagers", validationLevel: "moderate" })
db.villagers.updateOne({ name: "Julian" }, { $set: { nickname: "Jules" } })

// evolution: v2 stores the birthday as one string "5-30" and carries a version. Predict: does the validator let it through?
db.villagers.updateOne({ name: "Tom Nook" }, [
  { $set: { schemaVersion: 2, birthday: { $concat: [ { $toString: "$birthday.month" }, "-", { $toString: "$birthday.day" } ] } } }
])

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
db.villagers.updateMany({ schemaVersion: { $exists: false } }, [
  { $set: { schemaVersion: 2, birthday: { $concat: [ { $toString: "$birthday.month" }, "-", { $toString: "$birthday.day" } ] } } }
])
db.villagers.countDocuments({ schemaVersion: 2 })
db.villagers.findOne({ name: "Admiral" }, { birthday: 1, schemaVersion: 1 })
