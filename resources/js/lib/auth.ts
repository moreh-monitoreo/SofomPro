export function hasAccess(
    roles: Array<string>,
    userRoles: Array<{ name: String }>,
) {
    const userRolesFlat = userRoles.map((role) => role.name);

    return roles.some((role) => userRolesFlat.includes(role));
}
