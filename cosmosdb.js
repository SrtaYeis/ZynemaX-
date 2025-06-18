const { CosmosClient } = require('@azure/cosmos');
const sql = require('mssql');

class CosmosDBService {
    constructor() {
        const endpoint = process.env.AZURE_COSMOS_RESOURCEENDPOINT;
        const key = process.env.COSMOSDB_KEY;
        const databaseId = process.env.COSMOSDB_DATABASE;
        const containerPeliculaId = process.env.COSMOSDB_CONTAINER_PELICULA;
        const containerSedeId = process.env.COSMOSDB_CONTAINER_SEDE;

        this.client = new CosmosClient({ endpoint, key });
        this.database = this.client.database(databaseId);
        this.containerPelicula = this.database.container(containerPeliculaId);
        this.containerSede = this.database.container(containerSedeId);
    }

    async createReviewPelicula(dni, id_pelicula, comentario, puntuacion) {
        const item = {
            id: `${dni}_${id_pelicula}`,
            dni: dni,
            id_pelicula: id_pelicula,
            comentario: comentario,
            puntuacion: puntuacion
        };
        const { resource } = await this.containerPelicula.items.create(item);
        return resource;
    }

    async createReviewSede(dni, id_sede, comentario, puntuacion) {
        const item = {
            id: `${dni}_${id_sede}`,
            dni: dni,
            id_sede: id_sede,
            comentario: comentario,
            puntuacion: puntuacion
        };
        const { resource } = await this.containerSede.items.create(item);
        return resource;
    }

    async readReview(id, container) {
        const { resource } = await container.item(id).read();
        return resource;
    }
}

(async () => {
    const cosmosDB = new CosmosDBService();

    const dbConfig = {
        user: process.env.AZURE_SQLUID,
        password: process.env.AZURE_SQL_PWD,
        server: process.env.AZURE_SQL_SERVERNAME,
        database: process.env.AZURE_SQL_DATABASE,
        options: {
            encrypt: true,
            trustServerCertificate: true
        }
    };

    try {
        await sql.connect(dbConfig);
        const result = await sql.query`
            SELECT u.dni, f.id_pelicula, s.id_sede
            FROM Usuario u
            JOIN Reserva r ON u.dni = r.dni_usuario
            JOIN Reserva_funcion rf ON r.id_reserva = rf.id_reserva
            JOIN Funcion f ON rf.id_funcion = f.id_funcion
            JOIN Sala sa ON f.id_sala = sa.id_sala
            JOIN Sede s ON sa.id_sede = s.id_sede
            WHERE r.estado_reserva = 'completada'
        `;

        for (const row of result.recordset) {
            await cosmosDB.createReviewPelicula(row.dni, row.id_pelicula, "Gran película!", 4.5);
            await cosmosDB.createReviewSede(row.dni, row.id_sede, "Buena sede!", 4.0);
        }

        const reviewPelicula = await cosmosDB.readReview('1_1', cosmosDB.containerPelicula);
        if (reviewPelicula) {
            console.log('Reseña pelicula encontrada:', reviewPelicula);
        }
    } catch (err) {
        console.error('Error:', err);
    } finally {
        sql.close();
    }
})();
