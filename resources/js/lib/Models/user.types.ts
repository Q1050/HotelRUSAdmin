interface UserModel{
    firstName: string;
    lastName: string;
    formality?: string; // Optional field for formality (e.g., Mr., Ms., Dr.)
    initials?: string; // Optional field for initials, derived from firstName and last
    email: string;
    role: 'admin' | 'user'; // Example roles
    createdAt: string; // ISO date string
    updatedAt: string; // ISO date string
    // Add other fields as necessary
}